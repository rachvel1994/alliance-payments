import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo({ alt = 'Alliance Group', ...props }: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/images/alliance-group-logo.svg" width={94} height={120} alt={alt} decoding="async" {...props} />;
}
