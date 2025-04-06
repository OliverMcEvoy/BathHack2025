import { createRouter, createWebHistory } from 'vue-router';
import SpotifyPlayer from '../components/SpotifyPlayer.vue';
import SpotifyLogin from '../components/SpotifyLogin.vue';

const routes = [
    { 
        path: '/login',
        component: SpotifyLogin,
    },
    { 
        path: '/player',  // Dedicated player route - no code checks
        component: SpotifyPlayer,
    },
    { 
        path: '/',
        component: SpotifyPlayer,
        beforeEnter: async (to, from, next) => {
            // Check if URL has Spotify OAuth code (redirect from Spotify)
            const code = new URLSearchParams(window.location.search).get('code');
            
            if (code) {
                // Redirect to login page with the code
                next(`/login?code=${code}&state=${to.query.state || ''}`);
            } 
            // Normal auth check
            else if (!localStorage.getItem('access_token')) {
                next('/login');
            } else {
                next();
            }
        }
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;