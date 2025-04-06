<template>
    <div class="audio-controls" :class="{ darkMode }">
        <button class="control-button prev" @click="$emit('prevTrack')">
            <i class="fas fa-step-backward"></i>
        </button>
        <button class="control-button play" @click="$emit('togglePlay')"
            :style="{ background: `linear-gradient(135deg, ${gradientStart}, ${gradientEnd})` }"
            :disabled="audioLoading">
            <i :class="isPlaying ? 'fas fa-pause' : 'fas fa-play'"></i>
        </button>
        <button class="control-button next" @click="$emit('nextTrack')">
            <i class="fas fa-step-forward"></i>
        </button>
    </div>
</template>

<script>
export default {
    props: {
        isPlaying: Boolean,
        gradientStart: String,
        gradientEnd: String,
        audioLoading: Boolean,
        darkMode: Boolean, // New prop for dark mode
    },
};
</script>

<style scoped>
.control-button {
    color: #A78BFA;
    /* Purple-gray for control buttons */
}

.control-button i {
    color: black;
    /* Default icon color for normal mode */
}

.control-button.play {
    box-shadow: 0 4px 10px rgba(167, 139, 250, 0.3);
    /* Purple-gray shadow */
    background: linear-gradient(135deg, #B8E8FC, #FF8A8A);
    /* Adjust gradient for normal mode */
}

.control-button.play:hover {
    box-shadow: 0 6px 15px rgba(167, 139, 250, 0.5);
    /* Enhanced hover effect */
}

.control-button.play i {
    color: #FFFFFF;
    /* White icon for better contrast */
}

.control-button.darkMode i {
    color: #A78BFA;
    /* Purple icon color for dark mode */
}

.control-button.darkMode.play {
    background: linear-gradient(135deg, #6B46C1, #4C1D95);
    /* Dark purple gradient for dark mode */
}

.audio-controls.darkMode .control-button i {
    color: #A78BFA;
}
</style>
