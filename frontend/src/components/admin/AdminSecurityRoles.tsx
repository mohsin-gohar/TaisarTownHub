import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  ShieldCheck,
  Key,
  CheckCircle2,
  XCircle
} from 'lucide-react';
import { UserRole } from '../../types';

export const AdminSecurityRoles: React.FC = () => {
  const { activeRole, switchAuthUser } = useApp();
  const [presenterPin, setPresenterPin] = useState('');
  const [switching, setSwitching] = useState(false);

  const roles: { role: UserRole; title: string; desc: string; color: string }[] = [
    {
      role: 'super_admin',
      title: 'Super Admin',
      desc: 'Complete system control, role assignment, unmasked CNICs, private pricing, database migration, and staff management.',
      color: 'border-[#C5A46D]/60 bg-[#C5A46D]/20 text-[#F9E7BA]'
    },
    {
      role: 'admin',
      title: 'Agency Admin',
      desc: 'Full back-office operations: property CRUD, seller/buyer records, staff registration, task assignment, dealer payouts, and CMS builder.',
      color: 'border-[#C5A46D]/40 bg-[#C5A46D]/15 text-[#C5A46D]'
    }
  ];

  const permissionsMatrix = [
    { module: 'Public Listings & Search', super_admin: true, admin: true },
    { module: 'Property Listing Creation & Editing', super_admin: true, admin: true },
    { module: 'Private Purchase Prices & Margins', super_admin: true, admin: true },
    { module: 'Seller Records & Unmasked CNIC', super_admin: true, admin: true },
    { module: 'Buyer CRM & Lead Pipelines', super_admin: true, admin: true },
    { module: 'Dealer Commission Payout Execution', super_admin: true, admin: true },
    { module: 'Property File Legal Verification', super_admin: true, admin: true },
    { module: 'Plot Map Status Assignment', super_admin: true, admin: true },
    { module: 'Website CMS & Brand Customization', super_admin: true, admin: true },
    { module: 'Permanent Deletion & System Purge', super_admin: true, admin: false },
    { module: 'Staff Registration & Task Assignment', super_admin: true, admin: true },
    { module: 'User & Security Management', super_admin: true, admin: false },
  ];

  return (
    <div className="space-y-8 animate-fadeIn">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-gradient-to-r from-[#101C2C] to-[#1A2638] border border-[#C5A46D]/30 shadow-xl">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="font-serif-luxury text-2xl font-bold text-white">
              Role-Based Access Control (RBAC) & Security Architecture
            </h2>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D]/20 text-[#C5A46D] border border-[#C5A46D]/40">
              Enterprise Grade
            </span>
          </div>
          <p className="text-xs text-slate-300 mt-1">
            Enforcing field-level masking for CNICs, private purchase prices, dealer commissions, and server-side access controls.
          </p>
        </div>

        <div className="p-3 rounded-xl bg-[#0B1220] border border-slate-800 text-xs text-slate-300 flex items-center gap-2.5">
          <ShieldCheck className="w-5 h-5 text-[#C5A46D]" />
          <div>
            <span className="text-[10px] text-slate-400 block uppercase font-bold">Current Active Session</span>
            <span className="font-bold text-white capitalize">{activeRole.replace('_', ' ')}</span>
          </div>
        </div>
      </div>

      {/* Session Role Switcher */}
      <div className="bg-[#101C2C] p-6 rounded-2xl border border-slate-800 shadow-xl space-y-4">
        <div>
          <h3 className="font-serif-luxury text-lg font-bold text-white">
            Session Role Switcher
          </h3>
          <p className="text-xs text-slate-400">
            Switch the active session between administrative roles to review how access boundaries apply.
          </p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-3 items-center">
          {/* Presenter PIN gate */}
          <div className="p-3 rounded-xl border border-amber-500/30 bg-amber-500/5 space-y-2">
            <span className="text-[10px] font-bold text-amber-300 uppercase tracking-wider flex items-center gap-1.5">
              <Key className="w-3 h-3" />
              Presenter PIN
            </span>
            <input
              type="password"
              inputMode="numeric"
              value={presenterPin}
              onChange={(e) => setPresenterPin(e.target.value)}
              placeholder="PIN"
              aria-label="Presenter PIN for role switching"
              className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-amber-400 focus:outline-none font-mono tracking-widest"
            />
            <p className="text-[9px] text-slate-500 leading-snug">
              Role switching requires the presenter PIN configured for this deployment.
            </p>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
          {roles.map((item) => {
            const isActive = activeRole === item.role;

            return (
              <button
                key={item.role}
                onClick={async () => {
                  if (switching) return;
                  setSwitching(true);
                  try {
                    await switchAuthUser(item.role, presenterPin);
                  } finally {
                    setSwitching(false);
                  }
                }}
                disabled={switching}
                className={`p-4 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between disabled:opacity-60 ${
                  isActive
                    ? 'border-[#C5A46D] bg-[#C5A46D]/20 ring-2 ring-[#C5A46D] shadow-lg'
                    : 'border-slate-800 bg-[#0B1220] hover:border-slate-700'
                }`}
              >
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${item.color}`}>
                      {item.title}
                    </span>
                    {isActive && <CheckCircle2 className="w-4 h-4 text-[#C5A46D]" />}
                  </div>
                  <p className="text-[11px] text-slate-300 leading-relaxed">
                    {item.desc}
                  </p>
                </div>

                <div className="pt-3 mt-2 border-t border-slate-800 text-[10px] text-slate-400 font-bold">
                  {isActive ? '● Currently Active' : 'Switch to this role'}
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* Permissions Matrix Table */}
      <div className="bg-[#101C2C] p-6 rounded-2xl border border-slate-800 shadow-xl space-y-4">
        <div>
          <h3 className="font-serif-luxury text-lg font-bold text-white">
            Granular Permissions Matrix
          </h3>
          <p className="text-xs text-slate-400">
            Mapping of operational capabilities across Super Admin and Agency Admin roles.
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#0B1220] text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
              <tr>
                <th className="py-3 px-4">System Operation / Module</th>
                <th className="py-3 px-4 text-center">Super Admin</th>
                <th className="py-3 px-4 text-center">Agency Admin</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/80">
              {permissionsMatrix.map((row, idx) => (
                <tr key={idx} className="hover:bg-slate-800/40 transition-colors">
                  <td className="py-3 px-4 font-semibold text-slate-200">
                    {row.module}
                  </td>
                  <td className="py-3 px-4 text-center">
                    {row.super_admin ? (
                      <CheckCircle2 className="w-4 h-4 text-[#C5A46D] mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-slate-600 mx-auto" />
                    )}
                  </td>
                  <td className="py-3 px-4 text-center">
                    {row.admin ? (
                      <CheckCircle2 className="w-4 h-4 text-[#C5A46D] mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-slate-600 mx-auto" />
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Production Security Controls */}
      <div className="bg-[#101C2C] p-6 rounded-2xl border border-slate-800 shadow-xl space-y-4">
        <div>
          <h3 className="font-serif-luxury text-lg font-bold text-white flex items-center gap-2">
            <ShieldCheck className="w-5 h-5 text-[#C5A46D]" />
            <span>Production Security Controls</span>
          </h3>
          <p className="text-xs text-slate-400">
            Enforced by the Laravel API, Sanctum authentication, and the MySQL workspace layer.
          </p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {[
            { title: 'Server-side authorization', desc: 'Every mutation is checked against the signed-in role before it reaches the database.' },
            { title: 'Password hashing', desc: 'Passwords are stored with Laravel hashing; plaintext credentials are never persisted.' },
            { title: 'CNIC masking at rest', desc: 'National ID values are masked in API responses and unmasked only on an explicit, permissioned request.' },
            { title: 'Audit trail', desc: 'Logins, media sales, verification decisions, and destructive actions are recorded for review.' },
            { title: 'CORS allowlist', desc: 'API origins are restricted to the dashboard domains configured for this deployment.' },
            { title: 'Transport security', desc: 'Production deployments terminate TLS at the web server; keep HTTPS enforced for the dashboard and API.' },
          ].map((item) => (
            <div key={item.title} className="p-4 rounded-xl bg-[#0B1220] border border-slate-800">
              <div className="flex items-center gap-2 text-xs font-bold text-white">
                <CheckCircle2 className="w-4 h-4 text-[#C5A46D] shrink-0" />
                <span>{item.title}</span>
              </div>
              <p className="mt-1.5 text-[11px] text-slate-400 leading-relaxed">{item.desc}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
