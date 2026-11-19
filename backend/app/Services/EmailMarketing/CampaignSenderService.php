<?php

namespace App\Services\EmailMarketing;

use Illuminate\Support\Facades\{Config, Crypt, DB, Mail};
use Throwable;

class CampaignSenderService
{
    public function processar(): int
    {
        $campaigns = DB::table('email_marketing_campaigns')->whereIn('status', ['na_fila','enviando'])
            ->where(fn($q) => $q->whereNull('agendada_para')->orWhere('agendada_para','<=',now()))->orderBy('id')->limit(10)->get();
        $processed = 0;
        foreach ($campaigns as $campaign) $processed += $this->processarCampanha($campaign);
        return $processed;
    }

    private function processarCampanha(object $campaign): int
    {
        $settings = DB::table('email_marketing_settings')->where('empresa_id',$campaign->empresa_id)->where('active',true)->first();
        if (!$settings || !$settings->host || !$settings->from_email) return 0;
        $this->configure($settings);
        DB::table('email_marketing_campaigns')->where('id',$campaign->id)->update(['status'=>'enviando','iniciada_em'=>$campaign->iniciada_em ?: now(),'updated_at'=>now()]);
        $messages = DB::table('email_marketing_messages as m')->join('email_marketing_contacts as c','c.id','=','m.contact_id')
            ->where('m.campaign_id',$campaign->id)->where('m.status','pendente')->where('c.ativo',true)->where('c.consentimento',true)
            ->select('m.*','c.email','c.nome','c.empresa','c.cidade','c.unsubscribe_token')->limit(max(1,(int)$settings->rate_per_minute))->get();
        foreach ($messages as $message) $this->enviar($campaign,$message,$settings);
        $pending=DB::table('email_marketing_messages')->where('campaign_id',$campaign->id)->where('status','pendente')->count();
        $sent=DB::table('email_marketing_messages')->where('campaign_id',$campaign->id)->where('status','enviado')->count();
        $failed=DB::table('email_marketing_messages')->where('campaign_id',$campaign->id)->where('status','falhou')->count();
        DB::table('email_marketing_campaigns')->where('id',$campaign->id)->update(['enviados'=>$sent,'falhas'=>$failed,'status'=>$pending?'enviando':'concluida','concluida_em'=>$pending?null:now(),'updated_at'=>now()]);
        return $messages->count();
    }

    private function enviar(object $campaign, object $contact, object $settings): void
    {
        $url=rtrim((string)config('app.url'),'/').'/api/v1/email-marketing/descadastrar/'.$contact->unsubscribe_token;
        $replace=['{{nome}}'=>$contact->nome ?: 'Cliente','{{empresa}}'=>$contact->empresa ?: '','{{cidade}}'=>$contact->cidade ?: '','{{email}}'=>$contact->email,'{{descadastro_url}}'=>$url];
        $html=strtr($campaign->html,$replace).'<p style="font-size:11px;color:#777;text-align:center"><a href="'.e($url).'">Descadastrar deste tipo de comunicação</a></p>';
        try {
            Mail::mailer('campaign')->html($html,function($mail)use($campaign,$contact,$settings,$url){
                $mail->to($contact->email,$contact->nome)->subject($campaign->assunto)->from($settings->from_email,$settings->from_name ?: null);
                if($settings->reply_to)$mail->replyTo($settings->reply_to);
                $headers=$mail->getSymfonyMessage()->getHeaders();
                $headers->addTextHeader('List-Unsubscribe','<'.$url.'>');
                $headers->addTextHeader('List-Unsubscribe-Post','List-Unsubscribe=One-Click');
            });
            DB::table('email_marketing_messages')->where('id',$contact->id)->update(['status'=>'enviado','enviado_em'=>now(),'tentativas'=>DB::raw('tentativas + 1'),'erro'=>null,'updated_at'=>now()]);
        } catch(Throwable $e) {
            DB::table('email_marketing_messages')->where('id',$contact->id)->update(['status'=>'falhou','tentativas'=>DB::raw('tentativas + 1'),'erro'=>mb_substr($e->getMessage(),0,2000),'updated_at'=>now()]);
        }
    }

    private function configure(object $s): void
    {
        Config::set('mail.mailers.campaign',['transport'=>'smtp','scheme'=>$s->encryption === 'ssl' ? 'smtps' : 'smtp','host'=>$s->host,'port'=>$s->port,'username'=>$s->username_encrypted?Crypt::decryptString($s->username_encrypted):null,'password'=>$s->password_encrypted?Crypt::decryptString($s->password_encrypted):null,'timeout'=>30]);
    }
}
