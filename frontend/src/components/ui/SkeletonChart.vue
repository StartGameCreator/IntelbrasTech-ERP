<template>
  <div class="h-full w-full flex flex-col gap-3 justify-end p-2">
    <div class="flex items-end gap-2 h-full">
      <div v-for="i in 12" :key="i"
        class="skeleton flex-1 rounded-t"
        :style="{ height: `${Math.random() * 70 + 20}%` }"
      />
    </div>
    <div class="flex gap-2">
      <div v-for="i in 12" :key="i" class="skeleton flex-1 h-3 rounded" />
    </div>
  </div>
</template>
