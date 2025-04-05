<template>
  <div class="sidebar">
    <div class="logo">🎵 SoundScape</div>
    <div class="mood-selector-container">
      <label class="mood-label">MOOD</label>
      <select v-model="selectedMood" class="mood-selector" @change="$emit('mood-changed', selectedMood)">
        <option v-for="(color, mood) in moods" :key="mood" :value="mood">
          {{ mood }}
        </option>
      </select>
    </div>
    <div class="recent-tracks" v-if="recentTracks.length">
      <h3>RECENT TRACKS</h3>
      <div 
        v-for="(recentTrack, index) in recentTracks" 
        :key="index"
        class="recent-track"
        @click="$emit('play-recent', recentTrack)"
      >
        <img :src="recentTrack.album.images[2].url" class="recent-art" />
        <div class="recent-info">
          <div class="recent-title">{{ recentTrack.name }}</div>
          <div class="recent-artist">{{ recentTrack.artists[0].name }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Sidebar',
  props: {
    recentTracks: {
      type: Array,
      default: () => []
    },
    moods: {
      type: Object,
      required: true
    }
  },
  data() {
    return {
      selectedMood: 'Calm'
    }
  }
}
</script>

<style scoped>
.sidebar {
  background: rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(15px);
  padding: 2rem 1.5rem;
  border-right: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

/* Rest of sidebar styles from original file */
/* ... Copy all sidebar-related styles here ... */
</style>