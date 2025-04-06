import { createRouter, createWebHistory } from 'vue-router';
import SpotifyPlayer from '../components/SpotifyPlayer.vue';
import SpotifyLogin from '../components/SpotifyLogin.vue';
import Callback from '../components/Callback.vue'; // Import the Callback component

const routes = [
    { path: '/login', component: SpotifyLogin },
    { path: '/callback', component: Callback }, // Add the callback route
    {
        path: '/',
        component: SpotifyPlayer,
        beforeEnter: (to, from, next) => {
            const token = localStorage.getItem('spotify_token');
            console.log('Token in router guard:', token); // Debug log
            if (!token) {
                next('/login'); // Redirect to login if not authenticated
            } else {
                next(); // Proceed to SpotifyPlayer if authenticated
            }
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;