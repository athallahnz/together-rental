import type { ComponentProps } from 'react';

type AppLogoIconProps = Omit<ComponentProps<'img'>, 'src'>;

export default function AppLogoIcon({
    alt = 'Together Kamera',
    className = '',
    ...props
}: AppLogoIconProps) {
    return (
        <img
            src="/primary-logos.png"
            alt={alt}
            className={`object-contain ${className}`}
            draggable={false}
            {...props}
        />
    );
}
