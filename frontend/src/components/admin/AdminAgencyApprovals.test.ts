import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

// Source-level regression guards, matching the existing admin component tests.
// These never load application context, contact the backend, or make decisions.
const read = (path: string) => readFileSync(new URL(path, import.meta.url), 'utf8');
const desk = read('./AdminAgencyApprovals.tsx');
const layout = read('./AdminLayout.tsx');
const handler = desk.split('const handleDecision = async')[1].split('const disabled =')[0];
const loader = desk.split('const load = useCallback')[1].split('const handleDecision =')[0];
const transitions = desk.split('function availableDecisions')[1].split('export const AdminAgencyApprovals')[0];

test('agency approval navigation and lazy rendering require the enterprise grant', () => {
  assert.match(layout, /canManageAgencies = hasEnterprise\('admin\.manage_agencies'\)/);
  assert.match(layout, /\.\.\.\(canManageAgencies\s*\? \[\{ id: 'agency_approvals'/);
  assert.match(layout, /adminTab === 'agency_approvals' && canManageAgencies && <AdminAgencyApprovals/);
  assert.match(layout, /AdminAgencyApprovals = lazy\(\(\) => import\('\.\/AdminAgencyApprovals'\)/);
});

test('every decision requires confirmation and explicit backend acknowledgement', () => {
  const confirmation = handler.indexOf('await confirmAction(');
  const cancellation = handler.indexOf('if (!confirmed || !mounted.current) return;');
  const request = handler.indexOf('await api.verifyAdminAgency(');
  const acknowledgement = handler.indexOf("if (acknowledged !== true) throw new Error");
  const success = handler.indexOf('setNotice(`');
  assert.ok(confirmation >= 0 && cancellation > confirmation && request > cancellation);
  assert.ok(acknowledgement > request && success > acknowledgement);
  assert.match(handler, /verifyAdminAgency\(id, decision === 'approve' \? 'verified' : 'rejected', reason \|\| undefined\)/);
  assert.match(handler, /setAdminAgencyStatus\(id, decision === 'suspend' \? 'suspended' : 'active', reason \|\| undefined\)/);
  assert.ok(handler.indexOf('await load();') > success, 'reload server rows after acknowledgement');
});

test('decision requests are locked through confirmation and completion', () => {
  assert.match(handler, /if \(decisionInFlight\.current \|\| loading/);
  assert.ok(handler.indexOf('decisionInFlight.current = true') < handler.indexOf('await confirmAction('));
  assert.match(handler, /finally \{\s*decisionInFlight\.current = false/);
  assert.match(desk, /const disabled = loading \|\| Boolean\(busy\)/);
  assert.match(desk, /disabled=\{disabled \|\| \(\(decision === 'reject'/);
});

test('rejection and suspension require notes and reactivation requires verification', () => {
  assert.match(handler, /requiresReason = decision === 'reject' \|\| decision === 'suspend'/);
  assert.match(handler, /requiresReason && reason\.length < 3/);
  assert.match(transitions, /if \(agency\.verification_status === 'verified'\) \{[\s\S]*return \['suspend'\][\s\S]*return \['reactivate'\]/);
  const unverifiedBranch = transitions.split("if (['unverified', 'pending', 'rejected']")[1];
  assert.ok(unverifiedBranch);
  assert.doesNotMatch(unverifiedBranch, /return \['reactivate'\]/);
  assert.match(desk, /maxLength=\{1000\}/);
});

test('directory uses server filtering, pagination and honest loading failures', () => {
  assert.match(loader, /await api\.getAdminAgencies\(query\)/);
  assert.match(loader, /request !== requestId\.current/);
  assert.match(loader, /setAgencies\(\[\]\)/);
  assert.match(loader, /setPagination\(null\)/);
  assert.match(loader, /!body \|\| !Array\.isArray\(body\.agencies\)/);
  assert.match(loader, /setAgencies\(body\.agencies\)/);
  assert.match(loader, /catch \{[\s\S]*setLoadError/);
  assert.doesNotMatch(desk, /seed|localStorage|sessionStorage|window\.confirm/);
  assert.match(desk, /page: 1, search: searchDraft\.trim\(\)/);
  assert.match(desk, /page: 1, status: event\.target\.value/);
  assert.match(desk, /page: pagination\.current_page - 1/);
  assert.match(desk, /page: pagination\.current_page \+ 1/);
  assert.match(loader, /query\.page > body\.pagination\.last_page/);
  for (const status of ['pending', 'rejected', 'verified', 'suspended']) {
    assert.ok(desk.includes(`<option value="${status}">`));
  }
});

test('desk provides named controls, focus styles, distinct error and empty states', () => {
  assert.match(desk, /htmlFor=\{`\$\{fieldId\}-search`\}/);
  assert.match(desk, /htmlFor=\{`\$\{fieldId\}-status`\}/);
  assert.match(desk, /aria-describedby=\{`\$\{fieldId\}-\$\{id\}-hint`\}/);
  assert.match(desk, /aria-label=\{`\$\{decisionLabels\[decision\]\} \$\{agency\.name\}`\}/);
  assert.match(desk, /focus-visible:ring-2/);
  assert.match(desk, /role="alert"/);
  assert.match(desk, /aria-live="polite"/);
  assert.match(desk, /loadError \? \([\s\S]*Retry loading agencies[\s\S]*agencies\.length === 0/);
  for (const field of ['agency.email', 'agency.phone', 'agency.office_address', 'agency.city', 'agency.registration_number']) {
    assert.ok(desk.includes(field));
  }
});
