<template>
    <div class="valence-display">
        Valence: {{ valence.toFixed(2) }}
    </div>
</template>

<script>
export default {
    props: {
        valence: Number,
    },
};
</script>
