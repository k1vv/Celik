import CelikBrand from './CelikBrand';
import LearningPreview from './LearningPreview';
import { showcaseSlides } from './showcaseSlides';

export default function AuthShowcasePanel({ activeSlide, onSlideChange, paused, onPauseChange, hovered, onHoverChange }) {
    const slide = showcaseSlides[activeSlide];

    return (
        <section onMouseEnter={() => onHoverChange(true)} onMouseLeave={() => onHoverChange(false)} aria-label="Discover Celik" aria-roledescription="carousel" className="auth-showcase relative flex h-full min-h-0 flex-col overflow-hidden bg-linear-to-br from-auth-cream via-auth-soft to-auth-cream">
            <div aria-hidden="true" className="pointer-events-none absolute hidden lg:block -right-32 top-1/4 size-96 rounded-full bg-auth-lime/25 blur-3xl" />
            <header className="relative z-10 shrink-0"><CelikBrand /></header>
            <div className="auth-visual relative z-10 hidden min-h-0 flex-1 items-center justify-center lg:flex">
                <LearningPreview key={slide.id} type={slide.type} />
            </div>
            <footer className="relative z-10 shrink-0">
                <div key={slide.id} className="auth-slide" aria-live={paused || hovered ? 'polite' : 'off'} aria-atomic="true">
                    <p className="auth-eyebrow text-[10px] font-semibold tracking-[0.2em] text-auth-green lg:text-xs">{slide.eyebrow}</p>
                    <h2 className="auth-showcase-title font-medium tracking-tight">{slide.title}</h2>
                    <p className="auth-showcase-description text-sm leading-relaxed text-auth-muted">{slide.description}</p>
                </div>
                <div className="auth-showcase-controls flex items-center justify-between gap-2">
                    <span className="auth-badge rounded-full border border-auth-green/15 px-3 py-1.5 text-xs text-auth-green">{slide.badge}</span>
                    <div className="flex items-center gap-1">
                        {showcaseSlides.map((item, index) => (
                            <button key={item.id} type="button" onClick={() => onSlideChange(index)} aria-label={`Show ${item.title}`} aria-pressed={index === activeSlide} className="auth-focus flex size-6 items-center justify-center rounded-full">
                                <span className={`h-1.5 rounded-full transition-all duration-300 motion-reduce:transition-none ${index === activeSlide ? `auth-progress-indicator w-6 bg-auth-green ${paused ? '[animation-play-state:paused]' : ''}` : 'w-1.5 bg-auth-green/30'}`} />
                            </button>
                        ))}
                        <button type="button" onClick={() => onPauseChange(!paused)} aria-label={paused ? 'Resume showcase rotation' : 'Pause showcase rotation'} className="auth-focus ml-1 rounded px-1 py-1 text-xs font-medium text-auth-green">{paused ? 'Resume' : 'Pause'}</button>
                    </div>
                </div>
            </footer>
        </section>
    );
}
