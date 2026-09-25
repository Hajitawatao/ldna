<script setup>
import EmptyState from '../components/EmptyState.vue'
</script>

<template>
  <EmptyState icon="warning" title="This page doesn't exist" body="Check the address, or go back to the dashboard.">
    <RouterLink to="/" class="btn-primary">Go to dashboard</RouterLink>
  </EmptyState>
</template>
