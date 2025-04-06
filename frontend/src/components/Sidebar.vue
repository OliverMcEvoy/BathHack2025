<template>
    <div class="sidebar">
        <div class="logo">🎵 SoundScape</div>
        <div class="recent-tracks" v-if="recentTracks.length">
            <h3>RECENT TRACKS</h3>
            <div v-for="(recentTrack, index) in recentTracks" :key="index" class="recent-track"
                :class="{ loading: recentTrack.loading }" @click="$emit('playRecentTrack', recentTrack)">
                <div class="recent-art-wrapper">
                    <img v-if="recentTrack.album?.images?.length >= 3" :src="recentTrack.album.images[2].url"
                        class="recent-art" />
                    <div v-else class="recent-art-placeholder">
                        <i class="fas fa-music"></i>
                    </div>
                </div>
                <div class="recent-info">
                    <div class="recent-title">{{ recentTrack.name || 'Unknown Track' }}</div>
                    <div class="recent-artist">
                        {{ recentTrack.artists?.[0]?.name || 'Unknown Artist' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        recentTracks: Array,
    },
};
</script>
