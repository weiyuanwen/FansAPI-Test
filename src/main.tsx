// Ensure window.fetch has both getter and setter in iframe environments
try {
  if (typeof window !== 'undefined') {
    let _fetch = window.fetch ? window.fetch.bind(window) : undefined;
    Object.defineProperty(window, 'fetch', {
      get: () => _fetch,
      set: (val) => {
        _fetch = val;
      },
      configurable: true,
      enumerable: true,
    });
  }
} catch (_) {}

import {createRoot} from 'react-dom/client';
import App from './App.tsx';
import './index.css';

createRoot(document.getElementById('root')!).render(<App />);
