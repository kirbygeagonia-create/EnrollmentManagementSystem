export default function ApplicationLogo(props) {
    return (
        <svg
            {...props}
            viewBox="0 0 200 200"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
        >
            <defs>
                <linearGradient id="shPrimary" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FF6B35" />
                    <stop offset="100%" stop-color="#C94B15" />
                </linearGradient>
                <linearGradient id="shAccent" x1="0" y1="1" x2="1" y2="0">
                    <stop offset="0%" stop-color="#FFB347" />
                    <stop offset="100%" stop-color="#FF8C5A" />
                </linearGradient>
            </defs>
            {/* Shield shape with gradient */}
            <path
                d="M100 18 C68 18 38 30 24 52 C10 74 10 100 10 100 C10 132 12 152 30 166 C48 180 72 182 100 182 C128 182 152 180 170 166 C188 152 190 132 190 100 C190 100 190 74 176 52 C162 30 132 18 100 18Z"
                fill="url(#shPrimary)"
            />
            {/* Left page highlight */}
            <path
                d="M100 52 C88 54 66 62 54 74 L54 148 C66 138 88 132 100 132 Z"
                fill="white"
                opacity="0.16"
            />
            {/* Right page highlight */}
            <path
                d="M100 52 C112 54 134 62 146 74 L146 148 C134 138 112 132 100 132 Z"
                fill="white"
                opacity="0.10"
            />
            {/* Book spine line */}
            <rect x="72" y="94" width="56" height="8" rx="4" fill="white" opacity="0.95" />
            {/* Graduation cap */}
            <path
                d="M100 70 L118 91 L100 99 L82 91 Z"
                fill="white"
                opacity="0.95"
            />
            {/* Tassel line */}
            <line
                x1="116"
                y1="97"
                x2="116"
                y2="122"
                stroke="url(#shAccent)"
                strokeWidth="3"
                strokeLinecap="round"
            />
            {/* Tassel ball */}
            <circle cx="116" cy="126" r="4.5" fill="url(#shAccent)" />
        </svg>
    );
}