<template>
  <div class="search-container">
    <div class="search-box">
      <i class="fas fa-search search-icon"></i>
      <input
        v-model="searchInput"
        class="search-input"
        placeholder="Enter Spotify Track ID or Search..."
        @keyup.enter="$emit('search', searchInput)"
      />
      <button class="search-button" @click="$emit('search', searchInput)">
        {{ hasTrack ? 'Update' : 'Search' }}
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SearchBar',
  props: {
    hasTrack: Boolean
  },
  data() {
    return {
      searchInput: ''
    }
  }
}
</script>

<style scoped>
/* Copy all search-related styles from original file */
</style>