<template>
    <div class="sidebar" :class="{ collapsed, darkMode }">
        <div class="logo" v-if="!collapsed">Benjamin Hayward</div>
        <div class="recent-tracks" v-if="recentTracks.length && !collapsed">
            <h3>RECENT TRACKS</h3>
            <div v-for="(recentTrack, index) in recentTracks" :key="index" class="recent-track"
                :class="{ loading: recentTrack.loading }" @click="$emit('playRecentTrack', recentTrack)">
                <div class="recent-art-wrapper">
                    <img v-if="recentTrack.album?.images?.length >= 3" :src="recentTrack.album.images[2].url"
                        class="recent-art" />
                    <div v-else class="recent-art-placeholder">
                        <!-- Removed music icon -->
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
</template>

<script>
export default {
    props: {
        recentTracks: Array,
        collapsed: Boolean,
        darkMode: Boolean,
    },
};
</script>

<style>
.sidebar {
    width: 250px;
    transition: all 0.3s ease;
    overflow: hidden;
    background: #1A1A1A;
    /* Darker background */
    color: black;
    /* Default text color for normal mode */
}

.sidebar.collapsed {
    width: 0;
    padding: 0;
    border: none;
    background: #1A1A1A;
    color: black;
    /* Default text color for collapsed mode in normal mode */
}

.sidebar .logo {
    color: black;
    /* Default text color for normal mode */
    font-weight: bold;
    transition: opacity 0.3s ease;
}

.sidebar.collapsed .logo {
    opacity: 0;
}

.sidebar .recent-tracks {
    transition: opacity 0.3s ease;
}

.sidebar .recent-tracks h3 {
    color: black;
    /* Default text color for normal mode */
}

.sidebar.collapsed .recent-tracks {
    opacity: 0;
    pointer-events: none;
}

.sidebar .recent-track:hover {
    background: rgba(0, 0, 0, 0.1);
    /* Adjust hover effect for normal mode */
}

.sidebar .recent-title {
    font-size: 0.9rem;
    color: black;
    margin-bottom: 0.2rem;
    white-space: normal;
    /* Allow text to wrap */
    overflow: visible;
    /* Ensure wrapped text is visible */
    text-overflow: unset;
    /* Disable ellipsis for wrapping */
}

.sidebar .recent-artist {
    color: black;
    /* Default text color for normal mode */
}

.sidebar.darkMode {
    background: #1A1A1A;
    color: #A78BFA;
}

.sidebar.darkMode .logo,
.sidebar.darkMode .recent-tracks h3,
.sidebar.darkMode .recent-title,
.sidebar.darkMode .recent-artist {
    color: #A78BFA;
    /* Purple text color for dark mode */
}

.sidebar.darkMode .recent-track:hover {
    background: rgba(167, 139, 250, 0.2);
    /* Purple-gray hover effect for dark mode */
}
</style>
