<template>
    <div class="valence-display" :class="{ darkMode }">
        Valence: {{ valence.toFixed(2) }}
    </div>
</template>

<script>
export default {
    props: {
        valence: Number,
        darkMode: Boolean, // New prop for dark mode
    },
};
</script>

<style scoped>
.valence-display {
    background: rgba(18, 18, 18, 0.9);
    /* Darker black background */
    color: black;
    /* Default text color for normal mode */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.5);
    /* Slightly darker shadow */
}

.valence-display.darkMode {
    color: #A78BFA;
    /* Purple text color for dark mode */
}
</style>
