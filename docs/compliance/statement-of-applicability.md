# Statement of Applicability — DRAFT (not approved)

Control IDs follow ISO/IEC 27001:2022 Annex A numbering; names are short paraphrases, not the standard's text. Status: I = implemented, P = partial, N = not implemented, NA = not applicable, MD = requires management decision, EXT = requires external evidence. Owner "F" = founder, "D" = developer. Evidence refers to `docs/security/*`.

## Organizational (5.x)
| ID | Topic | Appl. | Status | Owner | Evidence / gap |
|---|---|---|---|---|---|
| 5.1 | Security policies | Y | N | F | Draft below in `isms.md`; not approved |
| 5.2 | Roles & responsibilities | Y | P | F | Owner named in IR plan; no RACI |
| 5.3 | Segregation of duties | Y | N | F | One person holds all access (R-08) |
| 5.4 | Management responsibilities | Y | MD | F | — |
| 5.5 | Contact with authorities | Y | P | F | Komdigi/PDP authority contact in IR plan pending |
| 5.6 | Special interest groups | Y | N | F | — |
| 5.7 | Threat intelligence | Y | P | D | composer/npm advisories via CI |
| 5.8 | Security in project management | Y | P | D | AGENTS.md rules |
| 5.9 | Asset inventory | Y | P | D | `00-system-inventory.md` |
| 5.10 | Acceptable use | Y | N | F | — |
| 5.11 | Return of assets | Y | N | F | — |
| 5.12 | Information classification | Y | N | F | Proposed in `isms.md` |
| 5.13 | Labelling | Y | N | F | — |
| 5.14 | Information transfer | Y | P | D | TLS; webhooks signed |
| 5.15 | Access control | Y | P | D | Roles, guards, 2FA staff |
| 5.16 | Identity management | Y | P | D | Unique accounts; PIN per cashier |
| 5.17 | Authentication information | Y | P | D | Hashed passwords, throttles |
| 5.18 | Access rights review | Y | N | F | No periodic review |
| 5.19 | Supplier security | Y | N | F | Supplier list in `privacy.md` |
| 5.20 | Security in supplier agreements | Y | N | F | DPAs missing |
| 5.21 | ICT supply chain | Y | P | D | Dependency audit CI |
| 5.22 | Supplier monitoring | Y | N | F | — |
| 5.23 | Cloud services security | Y | P | F | Hostinger, OpenAI; EXT |
| 5.24 | Incident planning | Y | P | F | `05-incident-response-plan.md` |
| 5.25 | Event assessment | Y | P | F | IR triage step |
| 5.26 | Incident response | Y | P | F | Not exercised |
| 5.27 | Learning from incidents | Y | P | F | Corrective action register |
| 5.28 | Evidence collection | Y | P | F | IR step 4 |
| 5.29 | Security during disruption | Y | N | F | BCP not written |
| 5.30 | ICT continuity readiness | Y | N | F | Backups unproven |
| 5.31 | Legal & contractual requirements | Y | P | F | UU PDP gap assessment; legal review needed |
| 5.32 | Intellectual property | Y | P | F | Public repo license review |
| 5.33 | Protection of records | Y | P | D | Void instead of delete in finance |
| 5.34 | Privacy & PII | Y | P | F | `privacy.md` |
| 5.35 | Independent review | Y | N | F | EXT |
| 5.36 | Compliance with policies | Y | N | F | — |
| 5.37 | Documented procedures | Y | P | D | DEPLOY docs, `04` |

## People (6.x)
| ID | Topic | Appl. | Status | Owner | Gap |
|---|---|---|---|---|---|
| 6.1 | Screening | Y | MD | F | Team size small |
| 6.2 | Employment terms | Y | N | F | — |
| 6.3 | Awareness & training | Y | N | F | — |
| 6.4 | Disciplinary process | Y | MD | F | — |
| 6.5 | After termination | Y | N | F | Offboarding checklist |
| 6.6 | Confidentiality/NDA | Y | N | F | — |
| 6.7 | Remote working | Y | N | F | Founder works remotely |
| 6.8 | Event reporting | Y | P | F | IR plan |

## Physical (7.x)
| ID | Topic | Appl. | Status | Gap |
|---|---|---|---|---|
| 7.1–7.5, 7.8, 7.11–7.13 | Perimeters, entry, offices, monitoring, threats, equipment siting, utilities, cabling, maintenance | NA for datacenter (Hostinger, EXT); office not in scope | NA/EXT | Hostinger attestations needed |
| 7.6 | Secure areas work | NA | NA | — |
| 7.7 | Clear desk/screen | Y | N | Founder laptop |
| 7.9 | Off-premises assets | Y | N | Laptop + merchant tablets guidance |
| 7.10 | Storage media | Y | N | Backup files on laptop must be encrypted |
| 7.14 | Disposal/reuse | Y | N | — |

## Technological (8.x)
| ID | Topic | Appl. | Status | Evidence / gap |
|---|---|---|---|---|
| 8.1 | User endpoints | Y | N | Laptop disk encryption unverified |
| 8.2 | Privileged access | Y | P | Platform guard + 2FA; server SSH single user |
| 8.3 | Information access restriction | Y | P | Tenant checks (`03`) |
| 8.4 | Source code access | Y | P | Public repo; secrets excluded; gitleaks CI |
| 8.5 | Secure authentication | Y | P | Throttles, 2FA staff; no MFA for merchants |
| 8.6 | Capacity management | Y | N | Shared hosting limits |
| 8.7 | Malware protection | Y | EXT | Hostinger |
| 8.8 | Technical vulnerabilities | Y | P | E-01..E-03, weekly CI |
| 8.9 | Configuration management | Y | P | `.env.example`; prod config unverified |
| 8.10 | Information deletion | Y | N | No deletion procedure |
| 8.11 | Data masking | Y | N | — |
| 8.12 | Data leakage prevention | Y | P | Tenant scoping |
| 8.13 | Backup | Y | N | `04` — pending |
| 8.14 | Redundancy | Y | N | Single host |
| 8.15 | Logging | Y | P | Activity logs, AI audit events |
| 8.16 | Monitoring | Y | N | No alerting |
| 8.17 | Clock sync | Y | EXT | Hostinger |
| 8.18 | Privileged utilities | Y | P | Artisan only via SSH |
| 8.19 | Software installation on systems | Y | EXT | Shared host |
| 8.20 | Network security | Y | EXT | Hostinger |
| 8.21 | Network services security | Y | P | TLS, signed webhooks |
| 8.22 | Network segregation | Y | EXT | — |
| 8.23 | Web filtering | NA | NA | No corporate network |
| 8.24 | Cryptography | Y | P | TLS, bcrypt, HMAC cookie; key mgmt undocumented |
| 8.25 | Secure SDLC | Y | P | Tests, CI |
| 8.26 | Application security requirements | Y | P | AGENTS.md, this program |
| 8.27 | Secure architecture | Y | P | `02` |
| 8.28 | Secure coding | Y | P | Reviews, tests |
| 8.29 | Security testing | Y | P | Regression tests (not executed yet) |
| 8.30 | Outsourced development | Y | P | AI agents follow AGENTS.md |
| 8.31 | Environment separation | Y | P | Separate app/DB from client site; no staging |
| 8.32 | Change management | Y | P | Git + CI deploy |
| 8.33 | Test information | Y | P | Factories, no prod data in tests |
| 8.34 | Audit testing protection | Y | P | No prod scanning performed |

Total: 37 + 8 + 14 + 34 = 93 controls.
