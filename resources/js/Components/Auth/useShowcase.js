import { useEffect, useState } from 'react';
import { showcaseSlides } from './showcaseSlides';

const AUTO_ROTATE_INTERVAL = 1000;

export default function useShowcase() {
    const [activeSlide, setActiveSlide] = useState(0);
    const [rotationKey, setRotationKey] = useState(0);
    const [paused, setPaused] = useState(false);
    const [hovered, setHovered] = useState(false);
    const [hidden, setHidden] = useState(false);

    useEffect(() => {
        const updateVisibility = () => setHidden(document.visibilityState !== 'visible');
        updateVisibility();
        document.addEventListener('visibilitychange', updateVisibility);
        return () => document.removeEventListener('visibilitychange', updateVisibility);
    }, []);

    useEffect(() => {
        if (paused || hovered || hidden || showcaseSlides.length <= 1) return;

        const timer = window.setInterval(() => {
            setActiveSlide((current) => (current + 1) % showcaseSlides.length);
        }, AUTO_ROTATE_INTERVAL);

        return () => window.clearInterval(timer);
    }, [rotationKey, paused, hovered, hidden]);

    const goToSlide = (index) => {
        setActiveSlide(index);
        setRotationKey((current) => current + 1);
    };

    return { activeSlide, goToSlide, paused, setPaused, hovered, setHovered };
}
