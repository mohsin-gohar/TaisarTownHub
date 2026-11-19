import React, { Component, ErrorInfo, ReactNode } from 'react';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  message: string;
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, message: '' };

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, message: error?.message || 'Unexpected error' };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // eslint-disable-next-line no-console
    console.error('ErrorBoundary caught an error:', error, info);
  }

  handleReset = () => {
    this.setState({ hasError: false, message: '' });
    window.location.reload();
  };

  render() {
    if (this.state.hasError) {
      if (this.props.fallback) return this.props.fallback;
      return (
        <div className="min-h-screen bg-[#07141E] text-slate-100 flex items-center justify-center p-6">
          <div className="max-w-md w-full bg-[#0B1220] border border-rose-500/40 rounded-2xl p-6 text-center space-y-4">
            <h1 className="text-xl font-bold text-rose-300">Something went wrong</h1>
            <p className="text-sm text-slate-300">
              The application hit an unexpected error. Your saved data is safe.
            </p>
            <p className="text-xs text-slate-500 font-mono break-all">{this.state.message}</p>
            <button
              onClick={this.handleReset}
              className="px-4 py-2 rounded-lg bg-[#E7C878] text-[#0B1220] font-bold text-sm"
            >
              Reload Application
            </button>
          </div>
        </div>
      );
    }
    return this.props.children;
  }
}