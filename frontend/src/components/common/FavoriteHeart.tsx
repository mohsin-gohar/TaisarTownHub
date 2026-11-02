import React, { useEffect, useState } from 'react';
import { Heart } from 'lucide-react';

const STORAGE_KEY = 'taisartownhub_v2_favorites';

const readFavorites = (): string[] => {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed.filter((v) => typeof v === 'string') : [];
  } catch {
    return [];
  }
};

const writeFavorites = (ids: string[]) => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
  } catch {
    /* storage unavailable — favorites remain session-only */
  }
};

interface FavoriteHeartProps {
  /** Stable record id (property/project). */
  id: string;
  title?: string;
  className?: string;
}

/**
 * Wishlist ("saved") toggle persisted in localStorage under the
 * app's namespaced key. Fully self-contained: no app state coupling.
 */
export const FavoriteHeart: React.FC<FavoriteHeartProps> = ({ id, title = 'Saved item', className }) => {
  const [isFavorite, setIsFavorite] = useState<boolean>(() => readFavorites().includes(id));

  // Keep in sync if another card instance updated the same id.
  useEffect(() => {
    const onStorage = (e: StorageEvent) => {
      if (e.key === STORAGE_KEY) setIsFavorite(readFavorites().includes(id));
    };
    window.addEventListener('storage', onStorage);
    return () => window.removeEventListener('storage', onStorage);
  }, [id]);

  const toggle = (e: React.MouseEvent) => {
    e.stopPropagation();
    const current = readFavorites();
    let next: string[];
    let nowFavorite: boolean;
    if (isFavorite) {
      next = current.filter((v) => v !== id);
      nowFavorite = false;
    } else {
      next = [...current, id];
      nowFavorite = true;
    }
    writeFavorites(next);
    setIsFavorite(nowFavorite);
  };

  return (
    <button
      onClick={toggle}
      aria-pressed={isFavorite}
      aria-label={isFavorite ? `Remove ${title} from favorites` : `Save ${title} to favorites`}
      title={isFavorite ? 'Saved to wishlist' : 'Save to wishlist'}
      className={`p-2 rounded-full backdrop-blur-md border transition-all cursor-pointer ${
        isFavorite
          ? 'bg-rose-500/90 border-rose-400 text-white shadow-lg'
          : 'bg-black/60 border-white/15 text-slate-200 hover:text-rose-300 hover:border-rose-400/60 opacity-0 group-hover:opacity-100 focus-visible:opacity-100'
      } ${className ?? ''}`}
    >
      <Heart className={`w-4 h-4 ${isFavorite ? 'fill-current' : ''}`} />
    </button>
  );
};
