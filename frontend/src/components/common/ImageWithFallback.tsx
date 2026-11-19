import React, { useState } from 'react';

interface Props extends React.ImgHTMLAttributes<HTMLImageElement> {
  fallback?: string;
}

export const ImageWithFallback: React.FC<Props> = ({ src, alt, fallback, className, ...rest }) => {
  const [error, setError] = useState(false);
  const fallbackSrc =
    fallback ||
    'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="100%" height="100%" fill="%230B1220"/><text x="50%" y="50%" fill="%23E7C878" font-family="sans-serif" font-size="18" text-anchor="middle" dominant-baseline="middle">Image unavailable</text></svg>';

  if (error || !src) {
    return <img src={fallbackSrc} alt={alt || 'image'} className={className} {...rest} />;
  }

  return (
    <img
      src={src}
      alt={alt || ''}
      className={className}
      onError={() => setError(true)}
      loading="lazy"
      {...rest}
    />
  );
};