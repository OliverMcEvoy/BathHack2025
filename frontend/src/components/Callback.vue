<template>
    <div class="callback">
        <h2>Processing Spotify Authentication...</h2>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    async mounted() {
        const queryParams = new URLSearchParams(window.location.search);
        const code = queryParams.get('code');
        const error = queryParams.get('error');

        if (error) {
            console.error('Spotify authentication error:', error);
            return;
        }

        if (code) {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/callback', {
                    params: { code },
                });
                console.log('Spotify access token:', response.data);
                localStorage.setItem('spotify_token', response.data.access_token);
                window.location.href = '/'; // Redirect to the main player
            } catch (err) {
                console.error('Error exchanging code for token:', err);
            }
        }
    },
};
</script>

<style scoped>
.callback {
    text-align: center;
    padding: 2rem;
}
</style>