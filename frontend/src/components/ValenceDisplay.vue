<template>
    <div class="valence-display" :class="{ darkMode }">
        <div>Valence: {{ valence.toFixed(2) }}</div>
        <div>Tempo: {{ tempo }}</div>
    </div>
</template>

<script>
export default {
    props: {
        valence: Number,
        tempo: Number,
        darkMode: Boolean, // Existing prop
    },
};
</script>

<style scoped>
.valence-display {
    background: rgba(18, 18, 18, 0.9);
    /* Darker black background */
    color: white;
    /* Changed text color to white */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.5);
    font-size: 1rem;
    padding: 10px 15px;
    border-radius: 8px;
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}

.valence-display.darkMode {
    color: white;
}
</style>
