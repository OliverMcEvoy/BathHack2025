<template>
    <div class="audio-controls">
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
    },
};
</script>
