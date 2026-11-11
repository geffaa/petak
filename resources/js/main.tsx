import 'maplibre-gl/dist/maplibre-gl.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import type { Endpoints } from './types';

const root = document.getElementById('app');

if (root) {
    const endpoints: Endpoints = {
        map: root.dataset.mapUrl ?? '',
        parcels: root.dataset.parcelsUrl ?? '',
        statistics: root.dataset.statisticsUrl ?? '',
    };

    createRoot(root).render(
        <StrictMode>
            <App endpoints={endpoints} />
        </StrictMode>,
    );
}
