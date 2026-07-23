<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TecnicoController;
use App\Http\Controllers\Auth\ClienteController;
use App\Http\Controllers\Auth\ColaboradorController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Tecnico\DashboardTecnicoController;
use App\Http\Controllers\Tecnico\RatController;
use App\Http\Controllers\Cliente\DashboardClienteController;
use App\Http\Controllers\Shared\ChatController;
use App\Http\Controllers\Shared\MeetingController;
use App\Http\Controllers\Shared\NotificacaoController;
use Illuminate\Support\Facades\Route;

// ── Home ──────────────────────────────────────────────────────────────────
Route::get('/', fn() => view('welcome'))->name('home');

// ── Guest ─────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

    Route::get('/auth/google',           [LoginController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback',  [LoginController::class, 'handleGoogleCallback'])->name('auth.google.callback');
    Route::get('/auth/microsoft',        [LoginController::class, 'redirectToMicrosoft'])->name('auth.microsoft');
    Route::get('/auth/microsoft/callback',[LoginController::class,'handleMicrosoftCallback'])->name('auth.microsoft.callback');

    Route::get('/cadastro',                    [TecnicoController::class,  'escolha'])->name('cadastro.escolha');
    Route::get('/cadastro/tecnico',            [TecnicoController::class,  'create'])->name('cadastro.tecnico');
    Route::post('/cadastro/tecnico',           [TecnicoController::class,  'store'])->name('cadastro.tecnico.store');
    Route::get('/cadastro/tecnico/sucesso',    [TecnicoController::class,  'sucesso'])->name('cadastro.tecnico.sucesso');
    Route::get('/cadastro/cliente',            [ClienteController::class,  'create'])->name('cadastro.cliente');
    Route::post('/cadastro/cliente',           [ClienteController::class,  'store'])->name('cadastro.cliente.store');
    Route::get('/cadastro/colaborador',        [ColaboradorController::class, 'create'])->name('cadastro.colaborador');
    Route::post('/cadastro/colaborador',       [ColaboradorController::class, 'store'])->name('cadastro.colaborador.store');

    Route::get('/esqueci-senha',  [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/esqueci-senha', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/redefinir-senha/{token}', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/redefinir-senha', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('password.update');
});

// ── Logout ────────────────────────────────────────────────────────────────
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ── Institucionais ────────────────────────────────────────────────────────
Route::get('/termos',      fn() => view('legal.termos'))->name('termos');
Route::get('/privacidade', fn() => view('legal.privacidade'))->name('privacidade');

// ── Técnico ───────────────────────────────────────────────────────────────
Route::middleware(['auth','role:tecnico','tecnico.aprovado'])->prefix('tecnico')->name('tecnico.')->group(function () {
    Route::get('/dashboard',  [DashboardTecnicoController::class, 'index'])->name('dashboard');
    Route::get('/chamados',   [DashboardTecnicoController::class, 'chamados'])->name('chamados');
    Route::get('/perfil',     [DashboardTecnicoController::class, 'perfil'])->name('perfil');
    Route::put('/perfil',     [DashboardTecnicoController::class, 'updatePerfil'])->name('perfil.update');
    Route::get('/avaliacoes', [DashboardTecnicoController::class, 'avaliacoes'])->name('avaliacoes');
    Route::get('/financeiro', [DashboardTecnicoController::class, 'financeiro'])->name('financeiro');
    Route::post('/online',    [DashboardTecnicoController::class, 'toggleOnline'])->name('online');
    Route::get('/rat/{chamado}',  [RatController::class, 'create'])->name('rat.create');
    Route::post('/rat/{chamado}', [RatController::class, 'store'])->name('rat.store');
    Route::get('/rat/{rat}/pdf',  [RatController::class, 'pdf'])->name('rat.pdf');
});

// ── Cliente ───────────────────────────────────────────────────────────────
Route::middleware(['auth','role:cliente'])->prefix('cliente')->name('cliente.')->group(function () {
    Route::get('/dashboard',          [DashboardClienteController::class, 'index'])->name('dashboard');
    Route::get('/chamados',           [DashboardClienteController::class, 'chamados'])->name('chamados');
    Route::post('/chamados',          [DashboardClienteController::class, 'abrirChamado'])->name('chamados.store');
    Route::get('/tecnicos',           [DashboardClienteController::class, 'tecnicos'])->name('tecnicos');
    Route::get('/avaliar/{chamado}',  [DashboardClienteController::class, 'avaliar'])->name('avaliar');
    Route::post('/avaliar/{chamado}', [DashboardClienteController::class, 'storeAvaliacao'])->name('avaliar.store');
});

// ── Admin ─────────────────────────────────────────────────────────────────
Route::middleware(['auth','role:admin,colaborador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard',  [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/tecnicos',   [AdminController::class, 'tecnicos'])->name('tecnicos');
    Route::get('/tecnicos/{id}',               [AdminController::class, 'showTecnico'])->name('tecnicos.show');
    Route::post('/tecnicos/{id}/aprovar',      [AdminController::class, 'aprovarTecnico'])->name('tecnicos.aprovar');
    Route::post('/tecnicos/{id}/rejeitar',     [AdminController::class, 'rejeitarTecnico'])->name('tecnicos.rejeitar');
    Route::post('/tecnicos/{id}/suspender',    [AdminController::class, 'suspenderTecnico'])->name('tecnicos.suspender');
    Route::get('/clientes',   [AdminController::class, 'clientes'])->name('clientes');
    Route::get('/chamados',   [AdminController::class, 'chamados'])->name('chamados');
    Route::get('/chamados/{id}',               [AdminController::class, 'showChamado'])->name('chamados.show');
    Route::post('/chamados/{id}/distribuir',   [AdminController::class, 'distribuirChamado'])->name('chamados.distribuir');
    Route::get('/relatorios', [AdminController::class, 'relatorios'])->name('relatorios');
    Route::get('/mapa',       [AdminController::class, 'mapa'])->name('mapa');
    Route::middleware('role:admin')->group(function () {
        Route::get('/colaboradores',             [AdminController::class, 'colaboradores'])->name('colaboradores');
        Route::post('/colaboradores/{id}/nivel', [AdminController::class, 'definirNivel'])->name('colaboradores.nivel');
    });
});

// ── Chat e Meeting (compartilhado) ────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/chamados/{id}/chat',         [ChatController::class, 'show'])->name('chat.show');
    Route::get('/chamados/{id}/mensagens',    [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chamados/{id}/mensagens',   [ChatController::class, 'store'])->name('chat.store');
    Route::post('/chamados/{id}/mensagens/anexo', [ChatController::class, 'storeAnexo'])->name('chat.anexo');
    Route::post('/chamados/{id}/meeting',     [MeetingController::class, 'create'])->name('meeting.create');
    Route::post('/rat/{rat}/assinar-cliente', [RatController::class, 'assinarCliente'])->name('rat.assinar.cliente');
});
