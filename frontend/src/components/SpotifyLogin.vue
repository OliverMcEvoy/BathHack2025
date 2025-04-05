<template>
    <div class="spotify-login">
        <div v-if="!isAuthenticated" class="login-container">
            <h2>Connect to Spotify</h2>
            <p>Please login to Spotify to use this player</p>
            <button @click="login" class="spotify-button">
                <i class="fab fa-spotify"></i>
                Login with Spotify
            </button>
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
            isAuthenticated: false
        }
    },
    methods: {
        async login() {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/authorize');
                if (response.data.authUrl) {
                    window.location.href = response.data.authUrl;
                }
            } catch (error) {
                console.error('Login failed:', error);
            }
        },
        async checkAuth() {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/check-auth');
                this.isAuthenticated = response.data.authenticated;
            } catch (error) {
                this.isAuthenticated = false;
            }
        }
    },
    mounted() {
        this.checkAuth();
    }
}
</script>

<style scoped>
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