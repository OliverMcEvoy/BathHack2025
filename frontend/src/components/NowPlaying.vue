<template>
    <div class="now-playing">
        <div class="track-column">
            <div class="track-info">
                <div class="album-art-container" :class="{ rotating: isPlaying }"
                    :style="{ transform: `rotate(${rotationAngle}deg)` }">
                    <img v-if="track.album?.images?.length" :src="track.album.images[0].url" :alt="track.name"
                        class="album-art" />
                    <div v-else class="album-art-placeholder">
                        <i class="fas fa-music"></i>
                    </div>
                </div>
                <h1 class="track-title">{{ track.name }}</h1>
                <p class="artist">{{track.artists?.map(a => a.name).join(', ') || 'Unknown Artist'}}</p>
                <AudioControls :isPlaying="isPlaying" :gradientStart="gradientStart" :gradientEnd="gradientEnd"
                    :audioLoading="audioLoading" @togglePlay="$emit('togglePlay')" @prevTrack="$emit('prevTrack')"
                    @nextTrack="$emit('nextTrack')" />
                <div class="progress-container">
                    <div class="progress-time left">{{ currentTimeFormatted }}</div>
                    <div class="progress-bar" @click="$emit('seekAudio', $event)">
                        <div class="progress" :style="{ width: progressPercentage + '%' }"></div>
                    </div>
                    <div class="progress-time right">{{ durationFormatted }}</div>
                </div>
                <div v-if="audioLoading" class="audio-state">
                    <i class="fas fa-spinner fa-spin"></i> Loading audio...
                </div>
                <div v-if="audioError" class="audio-state error">
                    <i class="fas fa-exclamation-triangle"></i> {{ audioError }}
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import AudioControls from './AudioControls.vue';

export default {
    components: {
        AudioControls,
    },
    props: {
        track: Object,
        isPlaying: Boolean,
        rotationAngle: Number,
        gradientStart: String,
        gradientEnd: String,
        audioLoading: Boolean,
        audioError: String,
        progressPercentage: Number,
        currentTimeFormatted: String,
        durationFormatted: String,
    },
};
</script>

<style scoped>
.album-art-container {
    width: 350px;
    /* Increased size */
    height: 350px;
    /* Increased size */
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto 1rem;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
}

.album-art-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}
</style>
