<template>
    <div class="spotify-desktop" :style="backgroundStyle">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">🎵 SoundScape</div>
            <div class="recent-tracks" v-if="recentTracks.length">
                <h3>RECENT TRACKS</h3>
                <div v-for="(recentTrack, index) in recentTracks" :key="index" class="recent-track"
                    :class="{ loading: recentTrack.loading }" @click="playRecentTrack(recentTrack)">
                    <div class="recent-art-wrapper">
                        <img v-if="recentTrack.album?.images?.length >= 3" :src="recentTrack.album.images[2].url"
                            class="recent-art" />
                        <div v-else class="recent-art-placeholder">
                            <i class="fas fa-music"></i>
                        </div>
                    </div>
                    <div class="recent-info">
                        <div class="recent-title">{{ recentTrack.name || 'Unknown Track' }}</div>
                        <div class="recent-artist">
                            {{ recentTrack.artists?.[0]?.name || 'Unknown Artist' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content centered">
            <!-- Start Listening Button -->
            <div v-if="!track && !audioLoading" class="start-listening">
                <button class="start-button" @click="startListening">Start Listening</button>
            </div>

            <!-- Now Playing Section -->
            <div v-if="track" class="now-playing">
                <!-- Track Info Column -->
                <div class="track-column">
                    <div class="track-info">
                        <!-- Album Art -->
                        <div class="album-art-container" :class="{ rotating: isPlaying }"
                            :style="{ transform: `rotate(${rotationAngle}deg)` }">
                            <img v-if="track.album?.images?.length" :src="track.album.images[0].url" :alt="track.name"
                                class="album-art" />
                            <div v-else class="album-art-placeholder">
                                <i class="fas fa-music"></i>
                            </div>
                        </div>

                        <!-- Track Title and Artist -->
                        <h1 class="track-title">{{ track.name }}</h1>
                        <p class="artist">{{track.artists?.map(a => a.name).join(', ') || 'Unknown Artist'}}</p>

                        <!-- Audio Controls -->
                        <div class="audio-controls">
                            <button class="control-button prev" @click="prevTrack">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button class="control-button play" @click="togglePlay"
                                :style="{ background: `linear-gradient(135deg, ${gradientStart}, ${gradientEnd})` }"
                                :disabled="audioLoading">
                                <i :class="isPlaying ? 'fas fa-pause' : 'fas fa-play'"></i>
                            </button>
                            <button class="control-button next" @click="fetchTrack">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>

                        <!-- Progress Bar -->
                        <div class="progress-container">
                            <div class="progress-time left">{{ currentTimeFormatted }}</div>
                            <div class="progress-bar" @click="seekAudio">
                                <div class="progress" :style="{ width: progressPercentage + '%' }"></div>
                            </div>
                            <div class="progress-time right">{{ durationFormatted }}</div>
                        </div>

                        <!-- Loading and Error States -->
                        <div v-if="audioLoading" class="audio-state">
                            <i class="fas fa-spinner fa-spin"></i> Loading audio...
                        </div>
                        <div v-if="audioError" class="audio-state error">
                            <i class="fas fa-exclamation-triangle"></i> {{ audioError }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="audioLoading" class="empty-state">
                <div class="empty-content">
                    <i class="fas fa-spinner fa-spin empty-icon"></i>
                    <h2>Loading...</h2>
                </div>
            </div>
        </div>

        <!-- Valence Display -->
        <div class="valence-display">
            Valence: {{ valence.toFixed(2) }}
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import OpenAI from "openai";

export default {
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
            backgroundAnimation: false, // For background animation
            interpolatedValence: 0.5, // Smoothly transitioning valence
            displayValence: 0.5, // Smoothly transitioning display valence
            rotationAngle: 0, // Current rotation angle in degrees
        };
    },
    computed: {
        backgroundStyle() {
            return this.track ? {
                background: `linear-gradient(135deg, ${this.gradientStart}, ${this.gradientEnd})`,
                transition: this.backgroundAnimation ? 'background 1.5s ease' : 'none',
            } : {};
        },
        currentTimeFormatted() {
            return this.formatTime(this.currentTime);
        },
        durationFormatted() {
            return this.formatTime(this.track?.duration_ms / 1000 || 0);
        },
        rotationSpeed() {
            // Map valence (0.0 to 1.0) to rotation speed (degrees per second)
            return 10 + (this.displayValence * 30); // Speed ranges from 30°/s to 300°/s
        }
    },
    methods: {
        async startListening() {
            this.audioLoading = true;
            await this.fetchTrack(); // Load the track in the background
            this.backgroundAnimation = true; // Trigger background animation
            this.audioLoading = false;
            this.togglePlay(); // Start playing the track
        },
        async fetchTrack() {
            try {
                this.audioError = null;

                // Get recommendation
                const recResponse = await axios.get('http://127.0.0.1:8000/spotify/rec');
                if (!recResponse.data?.recommendation) {
                    throw new Error('No track recommendation received');
                }
                this.trackId = recResponse.data.recommendation;

                // Get track details
                const trackResponse = await axios.get('http://127.0.0.1:8000/spotify/track', {
                    params: { track_id: this.trackId }
                });

                if (!trackResponse.data?.id || !trackResponse.data?.artists) {
                    throw new Error('Invalid track data structure');
                }

                // Process track data
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

                    // Listen for track end and fetch the next track
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
                    this.updateGradient(true); // Smooth transition
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
                        this.updateGradient(true); // Smooth transition
                    } else {
                        throw new Error('Failed to fetch valence');
                    }
                } catch (error) {
                    console.error('Periodic valence fetch error:', error);
                }
            }, 5000); // Fetch valence every 15 seconds
        },

        updateGradient() {
            const valenceColorMap = [
                { valence: 0.0, color: ['#B8E8FC', '#D4F4FA'] }, // Light blue
                { valence: 0.5, color: ['#D4C4F4', '#F5C6E6'] }, // Purple
                { valence: 1.0, color: ['#FFB5B5', '#FF8A8A'] }  // Red
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

                    // Error handling
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
                this.currentTime = seekTime; // Update current time
            }
        },

        updateRotation() {
            if (this.isPlaying) {
                const delta = this.rotationSpeed / 120; // Calculate the angle increment per frame (120 FPS)
                this.rotationAngle = (this.rotationAngle + delta) % 360; // Keep the angle within 0-360 degrees
            }
            setTimeout(this.updateRotation, 1000 / 120); // Schedule the next frame (120 FPS)
        }
    },
    mounted() {
        this.fetchValencePeriodically(); // Start periodic valence fetching
        this.initializeSpotifyPlayer();

        // Smoothly update display valence and gradient every frame
        const updateDisplayValence = () => {
            const diff = this.valence - this.displayValence;
            this.displayValence += diff * 0.01; // Incrementally approach the actual valence by a very small amount
            this.updateGradient(); // Update gradient based on displayValence
            setTimeout(updateDisplayValence, 100); // Run every 0.1 seconds
        };
        updateDisplayValence();

        // Start the rotation update loop
        this.updateRotation();
    },
    beforeUnmount() {
        if (this.valenceInterval) clearInterval(this.valenceInterval); // Clear interval on unmount
        if (this.player) {
            this.player.disconnect();
        }
    }
};
</script>

<style>
/* Base Layout for 16:9 Desktop */
.spotify-desktop {
    display: grid;
    grid-template-columns: 250px 1fr;
    height: 100%;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    transition: background 0.5s ease;
}

html,
body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden;
}

/* Sidebar Styles */
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

/* Main Content Styles */
.main-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
}

/* Start Listening Button */
.start-listening {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
}

.start-button {
    background: linear-gradient(135deg, #FF5733, #FF8D1A);
    /* Updated color */
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

/* Center the Now Playing Section */
.now-playing.centered {
    margin: auto;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

/* Valence Display */
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

/* Album Art Styles */
.album-art-container {
    width: 300px;
    /* Keep the increased size */
    height: 300px;
    /* Keep the increased size */
    border-radius: 50%;
    /* Maintain rounded corners */
    overflow: hidden;
    margin: 0 auto 1rem;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
    /* Add a background color for contrast */
}

.album-art-container img {
    width: 100%;
    /* Scale the image to fit the container */
    height: 100%;
    /* Scale the image to fit the container */
    object-fit: cover;
    /* Ensure the image covers the container without distortion */
    object-position: center;
    /* Center the image within the container */
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
    /* Align buttons horizontally */
    justify-content: center;
    gap: 1rem;
    /* Add spacing between buttons */
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
    /* Move further below */
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
    /* Updated gradient */
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

.main-content.centered {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    /* Center in the entire viewport */
    text-align: center;
}
</style>