<template>
    <div class="spotify-login">
        <div v-if="!isAuthenticated" class="login-container">
            <h2>Connect to Spotify</h2>
            <p>Please login to Spotify to use this player</p>
            <button @click="loginWithSpotify" class="spotify-button">
                Login with Spotify
            </button>
            <p v-if="authError" class="error-message">{{ authError }}</p>
        </div>
        <div v-else>
            <slot></slot>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'SpotifyLogin',
    data() {
        return {
            isAuthenticated: false,
            authError: null
        };
    },
    methods: {
        async loginWithSpotify() {
            this.authError = null;
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/authorize');
                if (response.data.authUrl) {
                    window.location.href = response.data.authUrl;
                } else {
                    this.authError = 'Failed to get Spotify authorization URL.';
                }
            } catch (error) {
                this.authError = 'Login failed. Please try again.';
                console.error('Login error:', error);
            }
        },
        async checkAuth() {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/check-auth');
                this.isAuthenticated = response.data.authenticated;
            } catch (error) {
                this.isAuthenticated = false;
                console.error('Auth check failed:', error);
            }
        },
        async handleSpotifyCallback() {
            const params = new URLSearchParams(window.location.search);
            const error = params.get('error');
            const code = params.get('code');
            const state = params.get('state');

            // If error (user denied access)
            if (error) {
                this.authError = `Spotify error: ${error}`;
                return;
            }

            // If successful (code exists)
            if (code && state) {
                this.isProcessingCallback = true; // Show loading state

                try {
                    // Exchange code for token

                    console.log('got here')

                    const response = await axios.get('http://127.0.0.1:8000/spotify/callback', {
                        params: { code, state },
                    });

                    console.log(localStorage.getItem('access_token'));
                    if (true) {
                        // Store token

                        // Redirect to home
                        this.$router.push('/player');
                    } else {
                        // Show error and stay on login page
                        this.authError = response.data.error || 'Login failed';
                    }
                } catch (err) {
                    this.authError = 'Connection error. Please try again.';
                    console.error('Spotify callback error:', err);
                } finally {
                    this.isProcessingCallback = false;
                }
            }
        }
    },
    async mounted() {
        await this.checkAuth();
        await this.handleSpotifyCallback(); // Check if we were redirected back from Spotify
    }
};
</script>

<style scoped>
.error-message {
    color: red;
    margin-top: 10px;
}

.spotify-button {
    background-color: #1DB954;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.spotify-button:hover {
    background-color: #1ed760;
}

.spotify-login {
    text-align: center;
    padding: 2rem;
}

.login-container {
    max-width: 400px;
    margin: 0 auto;
}

.spotify-button {
    background-color: #1DB954;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 24px;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 0 auto;
}

.spotify-button:hover {
    background-color: #1ed760;
}
</style>