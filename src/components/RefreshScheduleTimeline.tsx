import React, { useState, useMemo } from 'react';
import {
  Clock,
  Calendar,
  AlertTriangle,
  CheckCircle2,
  TrendingUp,
  FastForward,
  RotateCcw,
  Sliders,
  Filter,
  ArrowRight,
  Info,
  Timer,
  Zap,
  Activity,
  Layers,
  ChevronRight
} from 'lucide-react';

export interface RefreshScheduleTimelineProps {
  currentLikes: number;
  currentUsername: string;
  lastSuccessfulRefreshAt: string | null;
  mode: 'broken' | 'fixed';
}

interface ProfileScheduleItem {
  id: string;
  username: string;
  displayName: string;
  likes: number;
  lastRefreshedHoursAgo: number;
  avatarBg: string;
}

const DEFAULT_PROFILES: ProfileScheduleItem[] = [
  {
    id: '1',
    username: 'gem_superstar',
    displayName: 'Gem Superstar',
    likes: 450000,
    lastRefreshedHoursAgo: 18,
    avatarBg: 'bg-purple-600',
  },
  {
    id: '2',
    username: 'boundary_creator',
    displayName: 'Boundary Creator',
    likes: 100000, // EXACT boundary: <= 100k -> 72h
    lastRefreshedHoursAgo: 24,
    avatarBg: 'bg-amber-600',
  },
  {
    id: '3',
    username: 'rising_talent',
    displayName: 'Rising Talent',
    likes: 99999, // Just below 100k -> 72h
    lastRefreshedHoursAgo: 45,
    avatarBg: 'bg-blue-600',
  },
  {
    id: '4',
    username: 'casual_creator',
    displayName: 'Casual Creator',
    likes: 12400,
    lastRefreshedHoursAgo: 66,
    avatarBg: 'bg-slate-700',
  },
  {
    id: '5',
    username: 'newbie_account',
    displayName: 'Newbie Creator',
    likes: 0,
    lastRefreshedHoursAgo: 71,
    avatarBg: 'bg-zinc-700',
  },
];

export const RefreshScheduleTimeline: React.FC<RefreshScheduleTimelineProps> = ({
  currentLikes,
  currentUsername,
  lastSuccessfulRefreshAt,
  mode,
}) => {
  // Sandbox tester state
  const [sandboxLikes, setSandboxLikes] = useState<number>(currentLikes);
  const [simulatedHoursElapsed, setSimulatedHoursElapsed] = useState<number>(6);
  const [tierFilter, setTierFilter] = useState<'ALL' | '24h' | '72h' | 'DUE_SOON'>('ALL');

  // Business logic function matching Profile::calculateIntervalForLikes($likes)
  const getIntervalForLikes = (likes: number): number => {
    return likes > 100000 ? 24 : 72;
  };

  const activeInterval = getIntervalForLikes(currentLikes);
  const sandboxInterval = getIntervalForLikes(sandboxLikes);

  // Combine Madison Ivy (current simulation) with sample profile roster
  const allProfiles = useMemo(() => {
    const activeItem: ProfileScheduleItem = {
      id: 'active-sim',
      username: currentUsername,
      displayName: 'Madison Ivy (Active Simulator)',
      likes: currentLikes,
      lastRefreshedHoursAgo: simulatedHoursElapsed,
      avatarBg: 'bg-indigo-600',
    };

    return [activeItem, ...DEFAULT_PROFILES];
  }, [currentUsername, currentLikes, simulatedHoursElapsed]);

  const filteredProfiles = useMemo(() => {
    return allProfiles.filter(p => {
      const interval = getIntervalForLikes(p.likes);
      const remaining = Math.max(0, interval - p.lastRefreshedHoursAgo);
      if (tierFilter === '24h') return interval === 24;
      if (tierFilter === '72h') return interval === 72;
      if (tierFilter === 'DUE_SOON') return remaining <= 12;
      return true;
    });
  }, [allProfiles, tierFilter]);

  // Fast forward simulation controls
  const handleFastForward = (hours: number) => {
    setSimulatedHoursElapsed(prev => Math.min(72, prev + hours));
  };

  const handleResetTimeline = () => {
    setSimulatedHoursElapsed(0);
  };

  return (
    <div className="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-6 shadow-2xl relative overflow-hidden">
      {/* Decorative top accent */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-purple-500 via-indigo-500 to-cyan-500 opacity-80" />

      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-4">
        <div>
          <div className="flex items-center gap-2 text-purple-400 font-semibold text-xs tracking-wider uppercase mb-1">
            <Calendar className="w-4 h-4" />
            Cadence Orchestration &amp; Queue Scheduler
          </div>
          <h2 className="text-lg font-bold text-white flex items-center gap-2">
            Profile Refresh Schedule Timeline (24h / 72h Rule)
          </h2>
          <p className="text-xs text-slate-300 mt-1 max-w-3xl leading-relaxed">
            Per FansAPI specification, creator profiles with <strong className="text-purple-300">strictly &gt; 100,000 likes</strong> refresh every <strong className="text-purple-300">24 hours</strong>, while all other profiles (including exact boundary 100,000) refresh every <strong className="text-cyan-300">72 hours</strong>.
          </p>
        </div>

        {/* Global Stats Pills */}
        <div className="flex items-center gap-2 font-mono text-[11px]">
          <span className="px-2.5 py-1 rounded bg-purple-950/40 border border-purple-800/60 text-purple-300 font-bold">
            Tier 1 (&gt;100k): 24 Hours
          </span>
          <span className="px-2.5 py-1 rounded bg-cyan-950/40 border border-cyan-800/60 text-cyan-300 font-bold">
            Tier 2 (&le;100k): 72 Hours
          </span>
        </div>
      </div>

      {/* Hero Visualizer: Active Simulated Profile Timeline */}
      <div className="bg-slate-950 border border-slate-800 rounded-xl p-5 space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-900 pb-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center font-bold text-sm text-white shadow-md shadow-indigo-600/30">
              MI
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="text-sm font-bold text-white">@{currentUsername}</span>
                <span
                  className={`text-[10px] font-mono px-2 py-0.5 rounded font-bold border ${
                    activeInterval === 24
                      ? 'bg-purple-500/20 text-purple-300 border-purple-500/40'
                      : 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40'
                  }`}
                >
                  {activeInterval === 24 ? 'TIER 1 VIP (24h INTERVAL)' : 'TIER 2 STANDARD (72h INTERVAL)'}
                </span>
              </div>
              <div className="text-xs text-slate-400 mt-0.5">
                Current Likes: <strong className="text-amber-300 font-mono">{currentLikes.toLocaleString()}</strong> | Last Sync: {lastSuccessfulRefreshAt ?? '2026-09-29 03:00:00 UTC'}
              </div>
            </div>
          </div>

          {/* Time Machine Controls */}
          <div className="flex items-center gap-1.5 bg-slate-900 p-1.5 rounded-lg border border-slate-800 text-xs">
            <span className="text-[10px] text-slate-400 font-mono px-1 flex items-center gap-1">
              <FastForward className="w-3 h-3 text-cyan-400" />
              Simulate Time:
            </span>
            <button
              onClick={() => handleFastForward(6)}
              className="px-2 py-1 rounded bg-slate-950 hover:bg-slate-800 text-[11px] font-mono text-slate-200 border border-slate-800 transition cursor-pointer"
            >
              +6h
            </button>
            <button
              onClick={() => handleFastForward(12)}
              className="px-2 py-1 rounded bg-slate-950 hover:bg-slate-800 text-[11px] font-mono text-slate-200 border border-slate-800 transition cursor-pointer"
            >
              +12h
            </button>
            <button
              onClick={() => handleFastForward(24)}
              className="px-2 py-1 rounded bg-slate-950 hover:bg-slate-800 text-[11px] font-mono text-slate-200 border border-slate-800 transition cursor-pointer"
            >
              +24h
            </button>
            <button
              onClick={handleResetTimeline}
              className="px-2 py-1 rounded bg-slate-950 hover:bg-slate-800 text-[11px] font-mono text-slate-400 hover:text-white border border-slate-800 transition cursor-pointer flex items-center gap-1"
              title="Reset simulated clock to 0h"
            >
              <RotateCcw className="w-2.5 h-2.5" />
              Reset
            </button>
          </div>
        </div>

        {/* Dynamic Timeline Bar (0h to 72h) */}
        <div className="space-y-2 pt-1">
          <div className="flex justify-between items-center text-xs font-mono text-slate-400">
            <span className="flex items-center gap-1 text-emerald-400 font-bold">
              <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping" />
              T+0h (Last Refresh)
            </span>
            <span className={activeInterval === 24 ? 'text-purple-400 font-bold' : 'text-slate-500'}>
              T+24h {activeInterval === 24 ? '★ DUE HERE (VIP)' : '(Threshold)'}
            </span>
            <span className="text-slate-600">T+48h</span>
            <span className={activeInterval === 72 ? 'text-cyan-400 font-bold' : 'text-slate-500'}>
              T+72h {activeInterval === 72 ? '★ DUE HERE (Standard)' : '(End Window)'}
            </span>
          </div>

          {/* Timeline Track */}
          <div className="relative w-full h-7 bg-slate-900 rounded-lg border border-slate-800 overflow-hidden flex items-center">
            {/* 24h Threshold Marker Zone */}
            <div
              className={`h-full border-r-2 border-dashed flex items-center justify-end pr-2 text-[10px] font-mono font-bold transition-all ${
                activeInterval === 24
                  ? 'w-[33.33%] bg-purple-950/40 border-purple-400 text-purple-300'
                  : 'w-[33.33%] bg-slate-900/50 border-slate-700 text-slate-500'
              }`}
            >
              24h
            </div>

            {/* 48h Marker Zone */}
            <div className="w-[33.33%] h-full border-r border-slate-800/80 flex items-center justify-end pr-2 text-[10px] font-mono text-slate-600">
              48h
            </div>

            {/* 72h Marker Zone */}
            <div
              className={`w-[33.34%] h-full flex items-center justify-end pr-3 text-[10px] font-mono font-bold transition-all ${
                activeInterval === 72
                  ? 'bg-cyan-950/40 text-cyan-300'
                  : 'text-slate-600'
              }`}
            >
              72h
            </div>

            {/* Simulated Elapsed Progress Fill */}
            <div
              className={`absolute top-0 bottom-0 left-0 transition-all duration-300 opacity-60 ${
                simulatedHoursElapsed >= activeInterval
                  ? 'bg-rose-500'
                  : activeInterval === 24
                  ? 'bg-purple-600'
                  : 'bg-cyan-600'
              }`}
              style={{ width: `${Math.min(100, (simulatedHoursElapsed / 72) * 100)}%` }}
            />

            {/* Active Pointer Marker */}
            <div
              className="absolute top-0 bottom-0 w-1 bg-white shadow-lg shadow-white/50 z-10 transition-all duration-300"
              style={{ left: `${Math.min(99, (simulatedHoursElapsed / 72) * 100)}%` }}
            >
              <div className="absolute -top-6 -translate-x-1/2 bg-white text-slate-950 px-1.5 py-0.5 rounded text-[10px] font-mono font-bold shadow">
                +{simulatedHoursElapsed}h
              </div>
            </div>
          </div>

          {/* Schedule Status & Countdown */}
          <div className="flex flex-wrap items-center justify-between text-xs pt-1 text-slate-300">
            <div className="flex items-center gap-2">
              <Clock className="w-3.5 h-3.5 text-indigo-400" />
              <span>Simulated Elapsed Time: <strong className="font-mono text-white">+{simulatedHoursElapsed} hours</strong></span>
              <span className="text-slate-500">•</span>
              <span>Cadence Target: <strong className="font-mono text-purple-300">{activeInterval} hours</strong></span>
            </div>

            <div className="flex items-center gap-2 font-mono">
              <span className="text-slate-400">Queue Fetch Status:</span>
              {simulatedHoursElapsed >= activeInterval ? (
                <span className="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/40 font-bold animate-pulse">
                  DUE NOW! DISPATCHING REFRESH JOB
                </span>
              ) : (
                <span className="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 font-bold">
                  Next Fetch in {activeInterval - simulatedHoursElapsed}h
                </span>
              )}
            </div>
          </div>
        </div>

        {/* Incident Degradation Banner if likes wiped to 0 in broken mode */}
        {currentLikes === 0 && mode === 'broken' && (
          <div className="p-3.5 rounded-lg bg-rose-950/40 border border-rose-800/80 text-rose-200 text-xs flex items-start gap-3 shadow-lg shadow-rose-950/30">
            <AlertTriangle className="w-5 h-5 text-rose-400 shrink-0 mt-0.5 animate-bounce" />
            <div className="space-y-1">
              <strong className="text-rose-300 font-bold block text-sm">
                CRITICAL INCIDENT IMPACT: Schedule Cadence Demoted by 48 Hours!
              </strong>
              <p className="text-[11px] text-rose-200/90 leading-relaxed">
                When the legacy broken handler wiped Madison Ivy's likes from <strong>120,000 to 0</strong>, the cadence policy evaluated <code>$likes &gt; 100000</code> as false. Her refresh cycle was immediately downgraded from <strong>24 hours to 72 hours</strong>. Production creators remained displaying stale, destroyed metrics for an entire additional <strong>48 hours</strong> before the scheduler attempted another fetch!
              </p>
            </div>
          </div>
        )}
      </div>

      {/* Interactive Threshold Sandbox (Explore the 100k Boundary) */}
      <div className="bg-slate-950 border border-slate-800 rounded-xl p-5 space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-900 pb-3">
          <div className="flex items-center gap-2">
            <Sliders className="w-4 h-4 text-cyan-400" />
            <span className="text-xs font-bold text-slate-200 uppercase tracking-wide">
              Cadence Policy Boundary Sandbox
            </span>
          </div>
          <div className="flex items-center gap-1.5">
            <span className="text-[11px] text-slate-400">Quick Test Boundaries:</span>
            <button
              onClick={() => setSandboxLikes(99999)}
              className="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-[10px] font-mono text-cyan-300 border border-cyan-800/40 transition cursor-pointer"
            >
              99,999 likes (&le;100k)
            </button>
            <button
              onClick={() => setSandboxLikes(100000)}
              className="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-[10px] font-mono text-amber-300 border border-amber-800/40 transition cursor-pointer"
            >
              100,000 likes (EXACT)
            </button>
            <button
              onClick={() => setSandboxLikes(100001)}
              className="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-[10px] font-mono text-purple-300 border border-purple-800/40 transition cursor-pointer"
            >
              100,001 likes (&gt;100k)
            </button>
            <button
              onClick={() => setSandboxLikes(120000)}
              className="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-[10px] font-mono text-indigo-300 border border-indigo-800/40 transition cursor-pointer"
            >
              120,000 (Madison Ivy)
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
          <div className="md:col-span-7 space-y-2">
            <div className="flex justify-between text-xs">
              <span className="text-slate-400">Simulate Custom Creator Likes:</span>
              <span className="font-mono font-bold text-amber-300">{sandboxLikes.toLocaleString()} likes</span>
            </div>
            <input
              type="range"
              min="0"
              max="250000"
              step="500"
              value={sandboxLikes}
              onChange={e => setSandboxLikes(parseInt(e.target.value, 10))}
              className="w-full h-1.5 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-indigo-500"
            />
            <div className="flex justify-between text-[9px] font-mono text-slate-500">
              <span>0 (Newbie)</span>
              <span className="text-cyan-400 font-bold">100k Threshold</span>
              <span>250k (Superstar)</span>
            </div>
          </div>

          <div className="md:col-span-5 bg-slate-900 p-3 rounded-lg border border-slate-800 flex items-center justify-between">
            <div>
              <span className="text-[10px] text-slate-400 block font-medium">Evaluated Cadence Interval:</span>
              <div className="text-lg font-bold font-mono text-white mt-0.5 flex items-center gap-1.5">
                <span className={sandboxInterval === 24 ? 'text-purple-300' : 'text-cyan-300'}>
                  {sandboxInterval} Hours
                </span>
                <span className="text-xs text-slate-400 font-sans font-normal">
                  ({sandboxInterval === 24 ? 'High Frequency' : 'Standard Frequency'})
                </span>
              </div>
            </div>

            <div className="text-right">
              <span className="text-[10px] text-slate-500 block">Unit Test Assertion:</span>
              <span
                className={`text-[10px] font-mono px-2 py-0.5 rounded font-bold border inline-block mt-0.5 ${
                  sandboxLikes > 100000
                    ? 'bg-purple-500/20 text-purple-300 border-purple-500/40'
                    : 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40'
                }`}
              >
                {sandboxLikes > 100000 ? 'assert(24h)' : 'assert(72h)'}
              </span>
            </div>
          </div>
        </div>

        {/* Explain Boundary Precision */}
        <div className="p-2.5 rounded-lg bg-slate-900/60 border border-slate-800/80 text-[11px] text-slate-300 flex items-start gap-2">
          <Info className="w-3.5 h-3.5 text-indigo-400 shrink-0 mt-0.5" />
          <p className="leading-relaxed">
            <strong>Critical Boundary Test Note:</strong> Per <code>ProfileRefreshSchedulePolicyTest.php</code>, a profile with <strong>exact 100,000 likes</strong> evaluates to <strong>72 hours</strong>, because the strict mathematical condition is <code>$likes &gt; 100000</code>. Only 100,001+ likes qualifies for 24-hour cadence.
          </p>
        </div>
      </div>

      {/* Multi-Profile Schedule Roster */}
      <div className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Layers className="w-4 h-4 text-emerald-400" />
            <h3 className="text-xs font-bold text-slate-200 uppercase tracking-wide">
              Production Profile Refresh Roster &amp; Queue Timeline
            </h3>
            <span className="text-[10px] text-slate-500 font-mono">
              ({filteredProfiles.length} profiles displayed)
            </span>
          </div>

          {/* Filter Pills */}
          <div className="flex items-center gap-1 bg-slate-950 p-1 rounded-lg border border-slate-800 text-xs">
            <button
              onClick={() => setTierFilter('ALL')}
              className={`px-2.5 py-1 rounded text-[11px] font-medium transition cursor-pointer ${
                tierFilter === 'ALL' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-white'
              }`}
            >
              All Profiles
            </button>
            <button
              onClick={() => setTierFilter('24h')}
              className={`px-2.5 py-1 rounded text-[11px] font-medium transition cursor-pointer ${
                tierFilter === '24h' ? 'bg-purple-600 text-white font-bold' : 'text-slate-400 hover:text-white'
              }`}
            >
              Tier 1 (&gt;100k / 24h)
            </button>
            <button
              onClick={() => setTierFilter('72h')}
              className={`px-2.5 py-1 rounded text-[11px] font-medium transition cursor-pointer ${
                tierFilter === '72h' ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400 hover:text-white'
              }`}
            >
              Tier 2 (&le;100k / 72h)
            </button>
            <button
              onClick={() => setTierFilter('DUE_SOON')}
              className={`px-2.5 py-1 rounded text-[11px] font-medium transition cursor-pointer ${
                tierFilter === 'DUE_SOON' ? 'bg-amber-600 text-white font-bold' : 'text-slate-400 hover:text-white'
              }`}
            >
              Due Soon (&le;12h)
            </button>
          </div>
        </div>

        {/* Profile Grid Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          {filteredProfiles.map(item => {
            const interval = getIntervalForLikes(item.likes);
            const remainingHours = Math.max(0, interval - item.lastRefreshedHoursAgo);
            const isDue = remainingHours === 0;
            const progressRatio = Math.min(100, Math.round((item.lastRefreshedHoursAgo / interval) * 100));

            return (
              <div
                key={item.id}
                className={`p-3.5 rounded-xl border bg-slate-950 transition space-y-3 ${
                  item.id === 'active-sim'
                    ? 'border-indigo-500/80 ring-1 ring-indigo-500/40 shadow-lg shadow-indigo-950/40'
                    : 'border-slate-800 hover:border-slate-700'
                }`}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2.5">
                    <div
                      className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white ${item.avatarBg}`}
                    >
                      {item.username.substring(0, 2).toUpperCase()}
                    </div>
                    <div>
                      <div className="flex items-center gap-1.5">
                        <span className="text-xs font-bold text-slate-200">@{item.username}</span>
                        {item.id === 'active-sim' && (
                          <span className="text-[9px] px-1.5 py-0.2 rounded bg-indigo-500/20 text-indigo-300 font-bold">
                            Active
                          </span>
                        )}
                      </div>
                      <span className="text-[10px] text-slate-400 font-mono">
                        {item.likes.toLocaleString()} likes
                      </span>
                    </div>
                  </div>

                  <span
                    className={`text-[10px] font-mono px-2 py-0.5 rounded font-bold border ${
                      interval === 24
                        ? 'bg-purple-500/20 text-purple-300 border-purple-500/40'
                        : 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40'
                    }`}
                  >
                    {interval}h Tier
                  </span>
                </div>

                {/* Progress bar towards next fetch */}
                <div className="space-y-1">
                  <div className="flex justify-between text-[10px] font-mono text-slate-400">
                    <span>Age: {item.lastRefreshedHoursAgo}h</span>
                    <span className={isDue ? 'text-rose-400 font-bold animate-pulse' : 'text-slate-300'}>
                      {isDue ? 'DUE NOW' : `In ${remainingHours}h`}
                    </span>
                  </div>
                  <div className="w-full bg-slate-900 h-1.5 rounded-full overflow-hidden border border-slate-800">
                    <div
                      className={`h-full transition-all duration-300 ${
                        isDue
                          ? 'bg-rose-500'
                          : interval === 24
                          ? 'bg-purple-500'
                          : 'bg-cyan-500'
                      }`}
                      style={{ width: `${progressRatio}%` }}
                    />
                  </div>
                </div>

                {/* Schedule Metric Row */}
                <div className="flex justify-between items-center text-[10px] font-mono pt-1 border-t border-slate-900 text-slate-400">
                  <span>Frequency: {interval === 24 ? '1x / day' : '1x / 3 days'}</span>
                  <span className="text-slate-300">
                    Cadence: {interval}h ({item.likes > 100000 ? '&gt;100k' : '&le;100k'})
                  </span>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};
