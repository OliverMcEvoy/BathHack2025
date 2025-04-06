<template>
    <div class="spotify-desktop" :class="{ collapsed }" :style="backgroundStyle">
        <!-- Dynamic bubbles with randomized properties -->
        <div v-for="(bubble, index) in bubbles" :key="index" class="bubble" :style="bubbleStyle(bubble)"></div>

        <Sidebar :recentTracks="recentTracks" :collapsed="collapsed" @playRecentTrack="playRecentTrack" />
        <button class="toggle-sidebar" @click="toggleSidebar">
            <i :class="collapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left'"></i>
        </button>
        <div class="main-content" :class="{ centered: collapsed }">
            <NowPlaying v-if="track" :track="track" :isPlaying="isPlaying" :rotationAngle="rotationAngle"
                :gradientStart="gradientStart" :gradientEnd="gradientEnd" :audioLoading="audioLoading"
                :audioError="audioError" :progressPercentage="progressPercentage"
                :currentTimeFormatted="currentTimeFormatted" :durationFormatted="durationFormatted"
                @togglePlay="togglePlay" @prevTrack="prevTrack" @nextTrack="fetchTrack" @seekAudio="seekAudio" />
            <div v-else-if="!track && !audioLoading" class="start-listening">
                <button class="start-button" @click="startListening">Start Listening</button>
            </div>
            <div v-else-if="audioLoading" class="empty-state">
                <div class="empty-content">
                    <i class="fas fa-spinner fa-spin empty-icon"></i>
                    <h2>Loading...</h2>
                </div>
            </div>
        </div>
        <ValenceDisplay :valence="valence" />
    </div>
</template>

<script>
import Sidebar from './Sidebar.vue';
import NowPlaying from './NowPlaying.vue';
import ValenceDisplay from './ValenceDisplay.vue';
import axios from 'axios';
import OpenAI from "openai";

export default {
    components: {
        Sidebar,
        NowPlaying,
        ValenceDisplay,
    },
    data() {
        return {
            trackId: '',
            track: null,
            valence: 0.5,
            player: null,
            deviceId: null,
            isPlaying: false,
            progressPercentage: 0,
            currentTime: 0,
            recentTracks: [],
            audioLoading: false,
            audioError: null,
            gradientStart: '#B8E8FC',
            gradientEnd: '#FDF6EC',
            previousValence: 0.5,
            valenceInterval: null,
            backgroundAnimation: false,
            interpolatedValence: 0.5,
            displayValence: 0.5,
            rotationAngle: 0,
            collapsed: false,
            bubbles: Array(20).fill().map(() => ({
                startX: Math.random(),
                startY: Math.random(),
                xMove: 30 + Math.random() * 100,
                yMove: 50 + Math.random() * 150,
                size: 0.8 + Math.random() * 1.2,
                scale: 0.8 + Math.random() * 0.8,
                animationDuration: 10 + Math.random() * 20
            })),
        };
    },
    computed: {
        backgroundStyle() {
            return this.track ? {
                background: `linear-gradient(135deg, ${this.gradientStart}, ${this.gradientEnd}, ${this.gradientStart})`,
                backgroundSize: '400% 400%',
                '--animation-duration': `${30 - (this.displayValence * 25)}s`,
                '--gradient-start': this.gradientStart,
                '--gradient-end': this.gradientEnd,
                transition: this.backgroundAnimation ? 'background 1.5s ease' : 'none',
                '--valence-speed': (1 + this.displayValence).toFixed(2),
            } : {};
        },
        currentTimeFormatted() {
            return this.formatTime(this.currentTime);
        },
        durationFormatted() {
            return this.formatTime(this.track?.duration_ms / 1000 || 0);
        },
        rotationSpeed() {
            return 10 + (this.displayValence * 30);
        }
    },
    methods: {
        bubbleStyle(bubble) {
            return {
                '--random-start-x': bubble.startX,
                '--random-start-y': bubble.startY,
                '--random-x-move': `${bubble.xMove}px`,
                '--random-y-move': `${bubble.yMove}px`,
                '--bubble-size': bubble.size,
                '--bubble-scale': bubble.scale,
                '--bubble-animation-duration': `${bubble.animationDuration / (1 + this.displayValence)}s`
            };
        },
        async startListening() {
            this.audioLoading = true;
            await this.fetchTrack();
            this.backgroundAnimation = true;
            this.audioLoading = false;
            this.togglePlay();
        },
        async fetchTrack() {
            try {
                this.audioError = null;
                const recResponse = await axios.get('http://127.0.0.1:8000/spotify/rec');
                if (!recResponse.data?.recommendation) {
                    throw new Error('No track recommendation received');
                }
                this.trackId = recResponse.data.recommendation;

                const trackResponse = await axios.get('http://127.0.0.1:8000/spotify/track', {
                    params: { track_id: this.trackId }
                });

                if (!trackResponse.data?.id || !trackResponse.data?.artists) {
                    throw new Error('Invalid track data structure');
                }

                this.track = {
                    ...trackResponse.data,
                    album: {
                        ...trackResponse.data.album,
                        images: trackResponse.data.album?.images || []
                    },
                    artists: trackResponse.data.artists || []
                };

                this.valence = trackResponse.data.valence ?? 0.5;
                this.updateGradient();
                this.addToRecentTracks(this.track);
                await this.setupAudio();

            } catch (error) {
                console.error('Fetch error:', error);
                this.audioError = error.message || 'Failed to load track';
            }
        },
        async setupAudio() {
            try {
                if (!this.player) {
                    await this.initializeSpotifyPlayer();
                }

                await this.player._options.getOAuthToken(async accessToken => {
                    const response = await fetch(`https://api.spotify.com/v1/me/player/play?device_id=${this.deviceId}`, {
                        method: 'PUT',
                        headers: {
                            'Authorization': `Bearer ${accessToken}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            uris: [`spotify:track:${this.trackId}`]
                        })
                    });

                    if (!response.ok) throw new Error('Playback start failed');
                    this.isPlaying = true;

                    this.player.addListener('player_state_changed', state => {
                        if (state && state.track_window.current_track && state.paused && state.position === 0) {
                            this.fetchTrack();
                        }
                    });
                });

            } catch (error) {
                console.error('Playback error:', error);
                this.audioError = 'Playback failed - check Premium status';
                this.isPlaying = false;
            }
        },
        async getOAuthToken(callback) {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/token');
                if (response.data.token) {
                    callback(response.data.token);
                } else {
                    throw new Error(response.data.error || 'Invalid token');
                }
            } catch (error) {
                console.error('Token error:', error);
                this.audioError = 'Failed to get Spotify token';
            }
        },
        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },
        async fetchValence() {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/valence');
                console.log('Valence response:', response.data);
                if (response.data?.valence !== undefined) {
                    this.previousValence = this.valence;
                    this.valence = response.data.valence;
                    this.updateGradient(true);
                } else {
                    throw new Error('Failed to fetch valence');
                }
            } catch (error) {
                console.error('Valence fetch error:', error);
            }
            const client = new OpenAI();

            const response = await client.responses.create({
                model: "gpt-4o",
                input: "Write a one-sentence bedtime story about a unicorn."
            });

            console.log(response.output_text);
        },
        async fetchValencePeriodically() {
            this.valenceInterval = setInterval(async () => {
                try {
                    const response = await axios.get('http://127.0.0.1:8000/spotify/valence');
                    console.log('Periodic valence response:', response.data);
                    if (response.data?.valence !== undefined) {
                        this.previousValence = this.valence;
                        this.valence = response.data.valence;
                        this.updateGradient(true);
                    } else {
                        throw new Error('Failed to fetch valence');
                    }
                } catch (error) {
                    console.error('Periodic valence fetch error:', error);
                }
            }, 5000);
        },
        updateGradient() {
            const valenceColorMap = [
                { valence: 0.0, color: ['#B8E8FC', '#D4F4FA'] },
                { valence: 0.5, color: ['#D4C4F4', '#F5C6E6'] },
                { valence: 1.0, color: ['#FFB5B5', '#FF8A8A'] }
            ];

            const interpolate = (start, end, ratio) => start + ratio * (end - start);

            for (let i = 0; i < valenceColorMap.length - 1; i++) {
                const start = valenceColorMap[i];
                const end = valenceColorMap[i + 1];

                if (this.displayValence >= start.valence && this.displayValence < end.valence) {
                    const ratio = (this.displayValence - start.valence) / (end.valence - start.valence);

                    this.gradientStart = this.interpolateColor(start.color[0], end.color[0], ratio);
                    this.gradientEnd = this.interpolateColor(start.color[1], end.color[1], ratio);
                    break;
                }
            }
        },
        interpolateColor(color1, color2, ratio) {
            const hexToRgb = (hex) => {
                const bigint = parseInt(hex.slice(1), 16);
                return [(bigint >> 16) & 255, (bigint >> 8) & 255, bigint & 255];
            };

            const rgbToHex = (r, g, b) => {
                return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
            };

            const rgb1 = hexToRgb(color1);
            const rgb2 = hexToRgb(color2);

            const interpolatedRgb = rgb1.map((c, i) => Math.round(c + ratio * (rgb2[i] - c)));
            return rgbToHex(...interpolatedRgb);
        },
        addToRecentTracks(track) {
            if (track?.id && track?.name) {
                const exists = this.recentTracks.some(t => t.id === track.id);
                if (!exists) {
                    this.recentTracks.unshift({
                        id: track.id,
                        name: track.name,
                        artists: track.artists || [],
                        album: {
                            images: track.album?.images || []
                        }
                    });
                    if (this.recentTracks.length > 5) this.recentTracks.pop();
                }
            }
        },
        async playRecentTrack(track) {
            if (track.loading) return;
            try {
                track.loading = true;
                this.trackId = track.id;
                await this.fetchTrack();
            } finally {
                track.loading = false;
            }
        },
        async initializeSpotifyPlayer() {
            if (this.player) return;

            const script = document.createElement('script');
            script.src = 'https://sdk.scdn.co/spotify-player.js';
            script.async = true;
            document.body.appendChild(script);

            return new Promise((resolve) => {
                window.onSpotifyWebPlaybackSDKReady = () => {
                    this.player = new window.Spotify.Player({
                        name: 'SoundScape Player',
                        getOAuthToken: cb => this.getOAuthToken(cb),
                        volume: 0.5
                    });

                    this.player.addListener('initialization_error', ({ message }) => {
                        this.audioError = `Player error: ${message}`;
                    });
                    this.player.addListener('authentication_error', ({ message }) => {
                        this.audioError = 'Auth failed - please refresh';
                    });
                    this.player.addListener('account_error', ({ message }) => {
                        this.audioError = 'Premium account required';
                    });

                    this.player.connect().then(success => {
                        if (success) {
                            console.log('Spotify player connected');
                            resolve();
                        }
                    });

                    this.player.addListener('ready', ({ device_id }) => {
                        this.deviceId = device_id;
                    });

                    this.player.addListener('player_state_changed', state => {
                        if (state) {
                            this.isPlaying = !state.paused;
                            this.currentTime = state.position / 1000;
                            this.progressPercentage = (state.position / state.duration) * 100;
                        }
                    });
                };
            });
        },
        async togglePlay() {
            if (!this.track || this.audioLoading) return;

            try {
                if (this.isPlaying) {
                    await this.player.pause();
                } else {
                    await this.player.resume();
                }
                this.isPlaying = !this.isPlaying;
            } catch (error) {
                console.error('Playback toggle error:', error);
                this.audioError = 'Playback control failed';
            }
        },
        prevTrack() {
            this.player.previousTrack();
        },
        nextTrack() {
            this.player.nextTrack();
        },
        seekAudio(event) {
            if (this.player && this.track) {
                const rect = event.currentTarget.getBoundingClientRect();
                const seekPosition = (event.clientX - rect.left) / rect.width;
                const seekTime = seekPosition * (this.track.duration_ms / 1000);
                this.player.seek(seekTime * 1000);
                this.currentTime = seekTime;
            }
        },
        updateRotation() {
            if (this.isPlaying) {
                const delta = this.rotationSpeed / 120;
                this.rotationAngle = (this.rotationAngle + delta) % 360;
            }
            setTimeout(this.updateRotation, 1000 / 120);
        },
        toggleSidebar() {
            this.collapsed = !this.collapsed;
        },
        updateProgress() {
            if (this.isPlaying && this.track) {
                this.currentTime += 0.5;
                if (this.currentTime > this.track.duration_ms / 1000) {
                    this.currentTime = this.track.duration_ms / 1000;
                }
                this.progressPercentage = (this.currentTime / (this.track.duration_ms / 1000)) * 100;
            }
            setTimeout(this.updateProgress, 500);
        },
    },
    mounted() {
        this.fetchValencePeriodically();
        this.initializeSpotifyPlayer();

        const updateDisplayValence = () => {
            const diff = this.valence - this.displayValence;
            this.displayValence += diff * 0.01;
            this.updateGradient();
            setTimeout(updateDisplayValence, 100);
        };
        updateDisplayValence();

        this.updateRotation();
        this.updateProgress();
    },
    beforeUnmount() {
        if (this.valenceInterval) clearInterval(this.valenceInterval);
        if (this.player) {
            this.player.disconnect();
        }
    }
};
</script>

<style>
@keyframes bubbleFloat {
    0% {
        transform: translate(-50%, -50%) scale(var(--bubble-scale, 1));
        opacity: 0.8;
    }

    25% {
        transform: translate(calc(-50% + var(--random-x-move)), calc(-50% - var(--random-y-move))) scale(calc(var(--bubble-scale, 1) * 1.3));
        opacity: 1;
    }

    50% {
        transform: translate(calc(-50% - var(--random-x-move)), calc(-50% - var(--random-y-move) * 2)) scale(calc(var(--bubble-scale, 1) * 1.5));
        opacity: 0.9;
    }

    75% {
        transform: translate(calc(-50% + var(--random-x-move) * 1.5), calc(-50% - var(--random-y-move) * 3)) scale(calc(var(--bubble-scale, 1) * 1.4));
        opacity: 0.7;
    }

    100% {
        transform: translate(calc(-50% - var(--random-x-move) * 2), calc(-50% - var(--random-y-move) * 4)) scale(calc(var(--bubble-scale, 1) * 1.6));
        opacity: 0;
    }
}

.bubble {
    position: absolute;
    width: calc(50px * var(--bubble-size, 1));
    height: calc(50px * var(--bubble-size, 1));
    background: rgba(255, 255, 255, 0.5);
    border-radius: 50%;
    animation: bubbleFloat var(--bubble-animation-duration, 15s) infinite ease-in-out;
    filter: blur(24px);
    /* Double the shadow blur */
    z-index: 1;
    mix-blend-mode: screen;
    top: calc(var(--random-start-y, 0.5) * 100%);
    left: calc(var(--random-start-x, 0.5) * 100%);
    transform: translate(-50%, -50%);
}

@keyframes gradientFlow {
    0% {
        background-position: 0% 50%;
    }

    25% {
        background-position: 50% 75%;
    }

    50% {
        background-position: 100% 50%;
    }

    75% {
        background-position: 50% 25%;
    }

    100% {
        background-position: 0% 50%;
    }
}

@keyframes float {

    0%,
    100% {
        transform: translate(0, 0) scale(1);
    }

    25% {
        transform: translate(120px, -60px) scale(0.95);
    }

    50% {
        transform: translate(-60px, 100px) scale(1.05);
    }

    75% {
        transform: translate(-100px, -120px) scale(0.9);
    }
}

.spotify-desktop {
    display: grid;
    grid-template-columns: 250px 1fr;
    height: 100%;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    animation: gradientFlow var(--animation-duration, 15s) ease-in-out infinite;
    background-size: 400% 400%;
    position: relative;
    transition: grid-template-columns 0.3s ease;
    z-index: 0;
}

.spotify-desktop.collapsed {
    grid-template-columns: 0 1fr;
}

.spotify-desktop::before,
.spotify-desktop::after {
    content: '';
    position: absolute;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.3;
    z-index: -1;
    animation: float 25s infinite ease-in-out;
}

.spotify-desktop::before {
    background: var(--gradient-start);
    top: 20%;
    left: 10%;
}

.spotify-desktop::after {
    background: var(--gradient-end);
    bottom: 20%;
    right: 10%;
}

html,
body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.sidebar {
    background: rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(15px);
    padding: 1.5rem 1rem;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.logo {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 2rem;
    color: #000;
    text-shadow: none;
}

.recent-tracks {
    margin-top: 1rem;
}

.recent-tracks h3 {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #000;
    margin-bottom: 1rem;
}

.recent-track {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.recent-track:hover {
    background: rgba(255, 255, 255, 0.1);
}

.recent-art {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    margin-right: 0.8rem;
}

.recent-info {
    flex: 1;
}

.recent-title {
    font-size: 0.9rem;
    color: #000;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.recent-artist {
    font-size: 0.8rem;
    color: #000;
}

.main-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
}

.main-content.centered {
    justify-content: center;
    align-items: center;
    text-align: center;
}

.start-listening {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
}

.start-button {
    background: linear-gradient(135deg, #FF5733, #FF8D1A);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 30px;
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(255, 87, 51, 0.3);
}

.start-button:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(255, 87, 51, 0.5);
}

.now-playing.centered {
    margin: auto;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.valence-display {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}

.album-art-container {
    width: 300px;
    height: 300px;
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

.track-title {
    font-size: 1.5rem;
    font-weight: bold;
    color: black;
    margin: 0.5rem 0;
}

.artist {
    font-size: 1rem;
    color: black;
}

.audio-controls {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
}

.control-button {
    background: transparent;
    border: none;
    color: black;
    font-size: 1.5rem;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.control-button:hover {
    transform: scale(1.2);
}

.control-button.play {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.control-button.play i {
    font-size: 1.5rem;
    color: white;
}

.progress-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 2rem;
    width: 100%;
    max-width: 600px;
}

.progress-bar {
    flex: 1;
    height: 10px;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 5px;
    margin: 0 1rem;
    overflow: hidden;
    cursor: pointer;
}

.progress {
    background: linear-gradient(135deg, #B8E8FC, #FF8A8A);
    height: 100%;
    transition: width 0.2s ease;
}

.progress-time {
    font-size: 0.9rem;
    color: black;
}

.progress-time.left {
    text-align: left;
    width: 50px;
}

.progress-time.right {
    text-align: right;
    width: 50px;
}

.toggle-sidebar {
    position: absolute;
    bottom: 20px;
    left: 20px;
    z-index: 1000;
    background: rgba(0, 0, 0, 0.1);
    border: none;
    padding: 0.5rem;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toggle-sidebar i {
    font-size: 1.2rem;
    color: #888;
}

.toggle-sidebar:hover {
    background: rgba(0, 0, 0, 0.15);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.empty-state {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
}

.empty-content {
    text-align: center;
}

.empty-icon {
    font-size: 3rem;
    color: #888;
    margin-bottom: 1rem;
}
</style>