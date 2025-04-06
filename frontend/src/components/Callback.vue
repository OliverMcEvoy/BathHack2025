<template>
    <div>
        <p>Redirecting...</p>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    async mounted() {
        try {
            const params = new URLSearchParams(window.location.search);
            const code = params.get('code');
            const state = params.get('state');

            if (!code || !state) {
                console.error('Missing code or state in callback URL');
                return;
            }

            const response = await axios.get('http://127.0.0.1:8000/spotify/callback', {
                params: { code, state },
            });

            if (response.data.success) {
                localStorage.setItem('spotify_toekn_2', response.data.token); // Store token
                console.log('Token stored:', response.data.token);
                this.$router.push('/'); // Redirect to the main page
            } else {
                console.error('Callback error:', response.data.error);
            }
        } catch (error) {
            console.error('Callback error:', error);
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