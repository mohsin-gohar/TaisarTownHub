# RBAC and Tenant Isolation Report

## Evidence
- HEAD: `2eb9dae8e81607e3e9b63f09a34d416f2ee78450`.
- Laravel suite: 266 passing tests, including agency membership, explicit platform-admin grants, inactive account overrides, workspace hardening and cross-tenant save behavior.
- At least one suite exercised denied users seeing public listings only and rejected cross-agency row persistence.

## Public marketplace fix
- Public property filtering is sale-only.
- Legacy `/projects` and `/project-detail` routes redirect to `/properties?purpose=sale`.

## Remaining tenant-verification gaps
- Live browser cross-agency attempts for properties, files, finance, clients and documents were not completed for every role.
- The frontend did not have configured, known credentials for all requested roles in this session.
- Existing 266-test Laravel coverage is strong evidence for server-side controls, but it does not replace documented browser role testing.
