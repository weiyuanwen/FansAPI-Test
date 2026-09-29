import React, { useState, useEffect } from 'react';
import {
  Server,
  Zap,
  Clock,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Play,
  RotateCcw,
  Sliders,
  Send,
  ShieldCheck,
  RefreshCw,
  Terminal,
  Activity,
  Layers,
  ArrowRight
} from 'lucide-react';

export interface MockServerProps {
  httpStatus: number;
  onHttpStatusChange: (status: number) => void;
  delayMs: number;
  onDelayMsChange: (delay: number) => void;
  retryAfter: string | null;
  onRetryAfterChange: (retryAfter: string | null) => void;
  rawJson: string;
  onRawJsonChange: (json: string) => void;
  mode: 'broken' | 'fixed';
  onExecuteTest: (status: number, delay: number, retryAfter: string | null, customBody?: string) => Promise<void> | void;
  isTesting: boolean;
  currentLikes: number;
  currentRevision: number;
}

const STATUS_PRESETS = [
  { code: 200, label: '200 OK', category: 'success', desc: 'Upstream returned valid creator payload' },
  { code: 429, label: '429 Too Many Requests', category: 'rate_limit', desc: 'Incident trigger: Rate limit exceeded without Retry-After header' },
  { code: 500, label: '500 Server Error', category: 'server_error', desc: 'Upstream crashed with empty body or 500 status' },
  { code: 502, label: '502 Bad Gateway', category: 'server_error', desc: 'Upstream gateway / reverse proxy failure' },
  { code: 503, label: '503 Service Unavailable', category: 'server_error', desc: 'Upstream under maintenance' },
  { code: 404, label: '404 Not Found', category: 'client_error', desc: 'Creator username not found or account deleted' },
  { code: 400, label: '400 Bad Request', category: 'client_error', desc: 'Invalid query parameters sent by worker' },
];

const DELAY_PRESETS = [
  { label: 'Fast (50ms)', value: 50, tag: 'Cache Hit' },
  { label: 'Normal (250ms)', value: 250, tag: 'Standard' },
  { label: 'Heavy Load (1.5s)', value: 1500, tag: 'Degraded' },
  { label: 'Near Timeout (4.5s)', value: 4500, tag: 'High Risk' },
  { label: 'Timeout Breach (5.5s)', value: 5500, tag: '> 5.0s Client Limit' },
];

export const MockServer: React.FC<MockServerProps> = ({
  httpStatus,
  onHttpStatusChange,
  delayMs,
  onDelayMsChange,
  retryAfter,
  onRetryAfterChange,
  rawJson,
  onRawJsonChange,
  mode,
  onExecuteTest,
  isTesting,
  currentLikes,
  currentRevision,
}) => {
  const [hasRetryHeader, setHasRetryHeader] = useState<boolean>(retryAfter !== null);
  const [customRetryVal, setCustomRetryVal] = useState<string>(retryAfter ?? '30');
  const [customStatusInput, setCustomStatusInput] = useState<string>(httpStatus.toString());
  const [testProgress, setTestProgress] = useState<number>(0);
  const [elapsedMs, setElapsedMs] = useState<number>(0);

  // Synchronize customStatusInput with httpStatus
  useEffect(() => {
    setCustomStatusInput(httpStatus.toString());
  }, [httpStatus]);

  // Handle live progress bar when testing
  useEffect(() => {
    if (!isTesting) {
      setTestProgress(0);
      setElapsedMs(0);
      return;
    }

    const startTime = Date.now();
    const interval = setInterval(() => {
      const elapsed = Date.now() - startTime;
      setElapsedMs(elapsed);
      const ratio = Math.min(100, Math.round((elapsed / Math.max(delayMs, 100)) * 100));
      setTestProgress(ratio);
    }, 30);

    return () => clearInterval(interval);
  }, [isTesting, delayMs]);

  const handleSelectStatus = (code: number) => {
    onHttpStatusChange(code);
    setCustomStatusInput(code.toString());

    // Contextually suggest response body if appropriate
    if (code === 429) {
      onRawJsonChange(
        JSON.stringify(
          {
            error: 'Too Many Requests',
            message: 'You have reached the maximum allowed request rate for this endpoint.',
            status: 429,
          },
          null,
          2
        )
      );
    } else if (code === 500) {
      onRawJsonChange(''); // Real incident was empty 500 body
    } else if (code === 404) {
      onRawJsonChange(
        JSON.stringify(
          {
            error: 'Not Found',
            message: 'User account does not exist or has been disabled.',
          },
          null,
          2
        )
      );
    }
  };

  const handleCustomStatusBlur = () => {
    const parsed = parseInt(customStatusInput, 10);
    if (!isNaN(parsed) && parsed >= 100 && parsed <= 599) {
      onHttpStatusChange(parsed);
    } else {
      setCustomStatusInput(httpStatus.toString());
    }
  };

  const toggleRetryHeader = (enabled: boolean) => {
    setHasRetryHeader(enabled);
    if (enabled) {
      onRetryAfterChange(customRetryVal || '30');
    } else {
      onRetryAfterChange(null);
    }
  };

  const handleRetryValChange = (val: string) => {
    setCustomRetryVal(val);
    if (hasRetryHeader) {
      onRetryAfterChange(val);
    }
  };

  const isTimeoutBreached = delayMs > 5000;
  const isHighLatency = delayMs >= 3000 && delayMs <= 5000;

  // Determine resilience outcome description
  const getResilienceOutcome = () => {
    if (isTimeoutBreached) {
      return {
        type: 'TIMEOUT',
        badge: 'bg-rose-500/20 text-rose-300 border-rose-500/40',
        title: 'ConnectionException (> 5.0s Timeout Exceeded)',
        action: mode === 'broken' ? 'Worker hung for 60s, starvation cascaded' : 'Caught as TransientUpstreamException, released with jitter',
        dbEffect: mode === 'broken' ? 'Worker crash / starvation' : 'Preserved 120,000 likes',
      };
    }
    if (httpStatus === 429) {
      return {
        type: 'RATE_LIMIT',
        badge: 'bg-amber-500/20 text-amber-300 border-amber-500/40',
        title: 'HTTP 429 Rate Limit Exceeded',
        action: mode === 'broken' ? 'Wiped likes to 0 or synchronous spin' : 'TransientUpstreamException -> Jitter Backoff (pow(2, attempt) + rand(2,8))',
        dbEffect: mode === 'broken' ? 'WIPED TO 0 LIKES!' : 'Preserved 120,000 likes',
      };
    }
    if (httpStatus >= 500) {
      return {
        type: 'SERVER_ERROR',
        badge: 'bg-rose-500/20 text-rose-300 border-rose-500/40',
        title: `HTTP ${httpStatus} Server Error`,
        action: mode === 'broken' ? 'Null-coalescing ($data["likes"] ?? 0) defaulted to 0' : 'TransientUpstreamException -> Job released for retry',
        dbEffect: mode === 'broken' ? 'WIPED TO 0 LIKES!' : 'Preserved 120,000 likes',
      };
    }
    if (httpStatus >= 400 && httpStatus < 500) {
      return {
        type: 'CLIENT_ERROR',
        badge: 'bg-purple-500/20 text-purple-300 border-purple-500/40',
        title: `HTTP ${httpStatus} Client Error`,
        action: mode === 'broken' ? 'Unhandled exception or corrupted write' : 'PermanentUpstreamException -> fail($e) without wasteful retry',
        dbEffect: mode === 'broken' ? 'Corrupted or unhandled' : 'Preserved 120,000 likes',
      };
    }
    return {
      type: 'SUCCESS',
      badge: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40',
      title: 'HTTP 200 OK Response',
      action: mode === 'broken' ? 'Vulnerable to schema drift (profile.likes)' : 'Strict DTO parsing & monotonic revision guard',
      dbEffect: mode === 'broken' ? 'Vulnerable to wipe' : 'Committed verified metrics',
    };
  };

  const outcome = getResilienceOutcome();

  return (
    <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-5 shadow-xl relative overflow-hidden">
      {/* Decorative top accent */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-indigo-500 to-purple-500 opacity-80" />

      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 pb-4 pt-1">
        <div className="flex items-center gap-2.5">
          <div className="p-2 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
            <Server className="w-4 h-4" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h3 className="text-sm font-bold text-white tracking-wide">
                Mock Upstream Server &amp; Resilience Bench
              </h3>
              <span className="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 flex items-center gap-1">
                <span className="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse" />
                SIMULATOR ONLINE
              </span>
            </div>
            <p className="text-xs text-slate-400 mt-0.5">
              Target Endpoint: <code className="text-indigo-300 font-mono">GET https://onlyfans.com/api2/v2/users/madison420ivy</code>
            </p>
          </div>
        </div>

        {/* Live Client Limits Badge */}
        <div className="flex items-center gap-2 font-mono text-[11px]">
          <div className="bg-slate-950 px-2.5 py-1 rounded-md border border-slate-800 text-slate-300">
            <span className="text-slate-500">Connect Timeout:</span> <strong className="text-cyan-400">3.0s</strong>
          </div>
          <div className="bg-slate-950 px-2.5 py-1 rounded-md border border-slate-800 text-slate-300">
            <span className="text-slate-500">Request Timeout:</span> <strong className="text-amber-400">5.0s</strong>
          </div>
        </div>
      </div>

      {/* Main Controls Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {/* Left Column: HTTP Status Code Config (6 cols) */}
        <div className="lg:col-span-6 space-y-3">
          <div className="flex items-center justify-between">
            <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
              <Zap className="w-3.5 h-3.5 text-amber-400" />
              <span>1. HTTP Response Status Code:</span>
            </label>
            <div className="flex items-center gap-1">
              <span className="text-[11px] text-slate-400 font-mono">Custom:</span>
              <input
                type="number"
                min="100"
                max="599"
                value={customStatusInput}
                onChange={e => setCustomStatusInput(e.target.value)}
                onBlur={handleCustomStatusBlur}
                className="w-16 bg-slate-950 border border-slate-800 rounded px-2 py-0.5 text-xs text-slate-100 font-mono text-center focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          {/* Quick Status Buttons */}
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {STATUS_PRESETS.map(preset => {
              const isSelected = httpStatus === preset.code;
              let borderClass = 'border-slate-800 hover:border-slate-700 bg-slate-950';
              let badgeColor = 'text-slate-400';

              if (preset.category === 'success') {
                badgeColor = 'text-emerald-400';
                if (isSelected) borderClass = 'border-emerald-500/80 bg-emerald-950/30 shadow-sm shadow-emerald-500/20';
              } else if (preset.category === 'rate_limit') {
                badgeColor = 'text-amber-400';
                if (isSelected) borderClass = 'border-amber-500/80 bg-amber-950/30 shadow-sm shadow-amber-500/20';
              } else if (preset.category === 'server_error') {
                badgeColor = 'text-rose-400';
                if (isSelected) borderClass = 'border-rose-500/80 bg-rose-950/30 shadow-sm shadow-rose-500/20';
              } else {
                badgeColor = 'text-purple-400';
                if (isSelected) borderClass = 'border-purple-500/80 bg-purple-950/30 shadow-sm shadow-purple-500/20';
              }

              return (
                <button
                  key={preset.code}
                  type="button"
                  onClick={() => handleSelectStatus(preset.code)}
                  className={`p-2 rounded-lg border text-left transition flex flex-col justify-between cursor-pointer ${borderClass} ${
                    isSelected ? 'ring-1 ring-indigo-500/40' : ''
                  }`}
                  title={preset.desc}
                >
                  <div className="flex items-center justify-between w-full">
                    <span className={`text-xs font-mono font-bold ${badgeColor}`}>{preset.code}</span>
                    {isSelected && <CheckCircle2 className="w-3 h-3 text-indigo-400" />}
                  </div>
                  <span className="text-[10px] text-slate-300 font-medium truncate mt-0.5">{preset.label.replace(/^\d+\s*/, '')}</span>
                </button>
              );
            })}
          </div>

          {/* Active Status Context Note */}
          <div className="p-2.5 rounded-lg bg-slate-950 border border-slate-800 text-[11px] text-slate-300 flex items-start gap-2">
            <Terminal className="w-3.5 h-3.5 text-indigo-400 mt-0.5 shrink-0" />
            <div>
              <span className="text-slate-400">Behavior under test: </span>
              {httpStatus === 429 && (
                <strong className="text-amber-300">
                  HTTP 429 Rate Limit — OnlyFansApiClient must throw TransientUpstreamException without synchronous blocking!
                </strong>
              )}
              {httpStatus === 500 && (
                <strong className="text-rose-300">
                  HTTP 500 Server Error — Must not cause null-coalescing ($data['likes'] ?? 0) wipe of creator metrics!
                </strong>
              )}
              {httpStatus === 200 && (
                <strong className="text-emerald-300">
                  HTTP 200 OK — Evaluates payload format (v10 legacy vs v11 nested) and validates monotonic revision guard.
                </strong>
              )}
              {httpStatus >= 400 && httpStatus < 500 && httpStatus !== 429 && (
                <strong className="text-purple-300">
                  HTTP {httpStatus} Client Error — Throws PermanentUpstreamException and marks job failed (fail($e)) without retry loops.
                </strong>
              )}
              {httpStatus > 500 && (
                <strong className="text-rose-300">
                  HTTP {httpStatus} Gateway Outage — Throws TransientUpstreamException, preserves database, and retries with jitter delay.
                </strong>
              )}
            </div>
          </div>
        </div>

        {/* Right Column: Latency / Delay Slider & Headers (6 cols) */}
        <div className="lg:col-span-6 space-y-3">
          <div className="flex items-center justify-between">
            <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
              <Clock className="w-3.5 h-3.5 text-cyan-400" />
              <span>2. Simulated Response Delay (Latency):</span>
            </label>
            <span
              className={`text-xs font-mono font-bold px-2 py-0.5 rounded border ${
                isTimeoutBreached
                  ? 'bg-rose-500/20 text-rose-300 border-rose-500/40 animate-pulse'
                  : isHighLatency
                  ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                  : 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40'
              }`}
            >
              {delayMs >= 1000 ? `${(delayMs / 1000).toFixed(2)}s` : `${delayMs}ms`}
              {isTimeoutBreached && ' (TIMEOUT!)'}
            </span>
          </div>

          {/* Latency Range Slider */}
          <div className="space-y-1.5 bg-slate-950 p-3 rounded-lg border border-slate-800">
            <div className="flex items-center gap-3">
              <span className="text-[10px] font-mono text-slate-500">0ms</span>
              <input
                type="range"
                min="0"
                max="6000"
                step="50"
                value={delayMs}
                onChange={e => onDelayMsChange(parseInt(e.target.value, 10))}
                className="w-full h-1.5 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-indigo-500"
              />
              <span className="text-[10px] font-mono text-slate-500">6000ms</span>
            </div>

            {/* Threshold Markers */}
            <div className="flex justify-between text-[9px] font-mono text-slate-400 pt-0.5">
              <span className="text-emerald-400">Normal (&lt;1s)</span>
              <span className="text-cyan-400">Connect Limit (3s)</span>
              <span className="text-rose-400 font-bold">Client Timeout (5s)</span>
            </div>
          </div>

          {/* Quick Latency Presets */}
          <div className="flex flex-wrap gap-1.5">
            {DELAY_PRESETS.map(preset => (
              <button
                key={preset.value}
                type="button"
                onClick={() => onDelayMsChange(preset.value)}
                className={`px-2 py-1 rounded text-[10px] font-mono transition cursor-pointer border ${
                  delayMs === preset.value
                    ? 'bg-indigo-600 text-white border-indigo-500 font-bold shadow-sm'
                    : 'bg-slate-950 hover:bg-slate-800 text-slate-300 border-slate-800'
                }`}
              >
                {preset.label}
              </button>
            ))}
          </div>

          {/* Response Headers Config */}
          <div className="bg-slate-950 p-2.5 rounded-lg border border-slate-800 flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <span className="text-[11px] font-semibold text-slate-300">Upstream Headers:</span>
              <label className="flex items-center gap-1.5 text-[11px] text-slate-300 cursor-pointer">
                <input
                  type="checkbox"
                  checked={hasRetryHeader}
                  onChange={e => toggleRetryHeader(e.target.checked)}
                  className="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-0"
                />
                <span>Include <code>Retry-After</code></span>
              </label>
            </div>

            {hasRetryHeader ? (
              <div className="flex items-center gap-1">
                <span className="text-[10px] font-mono text-slate-400">Seconds:</span>
                <input
                  type="text"
                  value={customRetryVal}
                  onChange={e => handleRetryValChange(e.target.value)}
                  className="w-14 bg-slate-900 border border-slate-800 rounded px-1.5 py-0.5 text-xs text-amber-300 font-mono text-center focus:outline-none focus:border-indigo-500"
                />
              </div>
            ) : (
              <span className="text-[10px] text-amber-400/90 font-mono italic">
                (Omitted — exactly matches real incident)
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Interactive Testing & Client Resilience Impact Bar */}
      <div className="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="space-y-0.5">
            <div className="flex items-center gap-2">
              <Activity className="w-4 h-4 text-emerald-400" />
              <span className="text-xs font-bold text-slate-200">
                Resilience Engine Forecast ({mode === 'broken' ? 'Legacy Broken Handler' : 'Robust Laravel 13 Pipeline'})
              </span>
              <span className={`text-[10px] font-mono px-2 py-0.5 rounded font-bold border ${outcome.badge}`}>
                {outcome.title}
              </span>
            </div>
            <p className="text-[11px] text-slate-400">
              Expected Client Action: <span className="text-slate-200 font-medium">{outcome.action}</span>
            </p>
          </div>

          {/* Action Trigger Button */}
          <button
            type="button"
            disabled={isTesting}
            onClick={() => onExecuteTest(httpStatus, delayMs, hasRetryHeader ? customRetryVal : null)}
            className="px-5 py-2.5 rounded-lg bg-gradient-to-r from-indigo-600 to-cyan-600 hover:from-indigo-500 hover:to-cyan-500 disabled:opacity-50 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/30 transition cursor-pointer"
          >
            {isTesting ? (
              <>
                <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                <span>Simulating Call ({elapsedMs}ms)...</span>
              </>
            ) : (
              <>
                <Send className="w-3.5 h-3.5 text-cyan-200" />
                <span>Send Request to Mock Upstream</span>
              </>
            )}
          </button>
        </div>

        {/* Live Progress Bar when testing */}
        {isTesting && (
          <div className="space-y-1 pt-1">
            <div className="flex justify-between text-[10px] font-mono text-slate-400">
              <span>Simulating network latency...</span>
              <span>{elapsedMs}ms / {delayMs}ms ({testProgress}%)</span>
            </div>
            <div className="w-full bg-slate-900 h-2 rounded-full overflow-hidden border border-slate-800">
              <div
                className={`h-full transition-all duration-75 ${
                  isTimeoutBreached ? 'bg-rose-500' : 'bg-gradient-to-r from-indigo-500 to-cyan-400'
                }`}
                style={{ width: `${testProgress}%` }}
              />
            </div>
          </div>
        )}

        {/* Forecast Comparison Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1 border-t border-slate-900 text-xs">
          <div className="p-2.5 rounded-lg bg-slate-900/60 border border-slate-800/80 space-y-1">
            <div className="flex items-center justify-between text-[11px]">
              <span className="text-slate-400 font-semibold flex items-center gap-1">
                <XCircle className="w-3 h-3 text-rose-400" />
                Legacy Broken Handler Effect:
              </span>
              <span className="font-mono text-rose-400 font-bold">
                {httpStatus >= 500 || httpStatus === 429 || (httpStatus === 200 && rawJson.includes('profile'))
                  ? 'WIPES DB TO 0'
                  : isTimeoutBreached
                  ? 'WORKER HUNG (60s)'
                  : 'Passes unvalidated'}
              </span>
            </div>
            <p className="text-[10px] text-slate-400">
              Synchronous execution without proper error classification wipes database state or cascades queue latency.
            </p>
          </div>

          <div className="p-2.5 rounded-lg bg-slate-900/60 border border-slate-800/80 space-y-1">
            <div className="flex items-center justify-between text-[11px]">
              <span className="text-slate-400 font-semibold flex items-center gap-1">
                <ShieldCheck className="w-3 h-3 text-emerald-400" />
                Protected Laravel 13 Effect:
              </span>
              <span className="font-mono text-emerald-400 font-bold">
                {outcome.dbEffect}
              </span>
            </div>
            <p className="text-[10px] text-slate-400">
              Monotonic guard, optimistic locking, and randomized exponential jitter preserve current {currentLikes.toLocaleString()} likes.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
