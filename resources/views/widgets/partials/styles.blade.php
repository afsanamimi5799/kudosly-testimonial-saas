{{--
    The stylesheet of the embedded widget.

    Every rule is scoped to a `kudosly-widget` class so that nothing can
    leak into a host website that embeds the widget, and nothing from the
    host website can leak into the widget. The stylesheet is only ever
    served inside the widget's own document.
--}}
<style>
    .kudosly-widget,
    .kudosly-widget * {
        box-sizing: border-box;
    }

    .kudosly-widget {
        display: block;
        font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        line-height: 1.5;
        padding: 1.25rem;
    }

    .kudosly-widget-masonry {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
        gap: 1rem;
        align-items: start;
    }

    .kudosly-widget-carousel {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        padding-bottom: 0.5rem;
    }

    .kudosly-widget-carousel .kudosly-widget-card {
        flex: 0 0 min(20rem, 80%);
        scroll-snap-align: start;
    }

    .kudosly-widget-card {
        display: flex;
        flex-direction: column;
        border-width: 1px;
        border-style: solid;
        border-radius: 0.75rem;
        padding: 1rem;
    }

    .kudosly-widget-head {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }

    .kudosly-widget-avatar {
        height: 2.5rem;
        width: 2.5rem;
        flex-shrink: 0;
        border-radius: 9999px;
        object-fit: cover;
    }

    .kudosly-widget-initials {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgb(224 231 255);
        color: rgb(67 56 202);
        font-size: 0.875rem;
        font-weight: 600;
    }

    .kudosly-widget-person {
        min-width: 0;
    }

    .kudosly-widget-name {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .kudosly-widget-company {
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.8125rem;
    }

    .kudosly-widget-rating {
        display: flex;
        gap: 0.125rem;
        margin-top: 0.75rem;
        color: rgb(203 213 225);
    }

    .kudosly-widget-star-on {
        color: rgb(245 158 11);
    }

    .kudosly-widget-text {
        margin: 0.75rem 0 0;
        flex: 1 1 auto;
        font-size: 0.875rem;
    }

    .kudosly-widget-empty {
        margin: 0;
        padding: 1.5rem 0;
        text-align: center;
        font-size: 0.875rem;
    }

    @media (prefers-reduced-motion: no-preference) {
        .kudosly-widget-animate .kudosly-widget-card {
            animation: kudosly-widget-fade 0.35s ease both;
        }
    }

    @keyframes kudosly-widget-fade {
        from {
            opacity: 0;
            transform: translateY(0.5rem);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }
</style>
