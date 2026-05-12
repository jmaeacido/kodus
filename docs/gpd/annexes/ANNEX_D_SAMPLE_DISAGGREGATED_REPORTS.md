# Annex D - Sample Disaggregated Reports

**Document status:** Draft with sanitized sample tables  
**System:** KliMalasakit Operational Data Unified System (KODUS)  
**Scope:** Sample report structures by sex, age group, disability classification, LGU/barangay, program/activity, and implementation status  
**Privacy note:** Tables below use synthetic aggregate figures only. No personal data or raw beneficiary rows are included.

## D.1 Purpose

This annex presents sanitized examples of disaggregated reports that may be generated or supported through KODUS data captured in the MEB, implementation-status, payout/fund monitoring, and summary modules. The examples show how aggregate reporting supports monitoring, management review, and decision-making.

## D.2 Source Modules and Relevant Fields

| Reporting area | KODUS source | Representative fields |
| --- | --- | --- |
| MEB beneficiary profile | `meb` records, `pages/export_meb.php`, `pages/summary/` | Sex, age, civil status, NHTS-PR, 4Ps, sectoral classifications, disability classification, location |
| MEB validation | `pages/export_meb_validation.php` | Province, municipality, barangay, target, imported actual, variance, validation status |
| Implementation status | `implementation-status/` | Fiscal year, program classification, project type, target, actual accomplishment, status, location, evidence links |
| LAWA/BINHI summaries | `implementation-status/lawa-summary.php`, `implementation-status/binhi-summary.php` | Target-versus-actual summaries and project metrics |
| Payout/fund monitoring | `pages/payout.php`, `pages/fund-monitoring.php` | Payout status, obligations, disbursements, utilization, object codes |

## D.3 Report by Sex

**Table D-1. Sample MEB count by sex, sanitized.**

| Sex | Beneficiary count | Percent share |
| --- | ---: | ---: |
| Female | 612 | 51.00% |
| Male | 582 | 48.50% |
| Not specified / for correction | 6 | 0.50% |
| **Total** | **1,200** | **100.00%** |

**Monitoring note:** Sex-disaggregated totals support gender-responsive monitoring, targeting review, and identification of records requiring correction.

## D.4 Report by Age Group

**Table D-2. Sample MEB count by age group, sanitized.**

| Age group | Beneficiary count | Percent share |
| --- | ---: | ---: |
| 18-30 | 240 | 20.00% |
| 31-45 | 420 | 35.00% |
| 46-59 | 330 | 27.50% |
| 60 and above | 190 | 15.83% |
| Not specified / for correction | 20 | 1.67% |
| **Total** | **1,200** | **100.00%** |

**Monitoring note:** Age grouping supports vulnerability analysis and helps identify records with missing or inconsistent birthdate/age values.

## D.5 Report by Disability and Sectoral Classification

**Table D-3. Sample sectoral/disability indicators, sanitized.**

| Classification | Indicator field | Beneficiary count | Note |
| --- | --- | ---: | --- |
| Persons with Disability | PWD | 86 | May overlap with other classifications |
| Senior Citizen | SC | 190 | Based on encoded classification/age validation |
| Solo Parent | SP | 74 | Subject to documentary validation |
| Indigenous People | IP | 128 | Requires culturally appropriate data handling |
| Farmers | F | 508 | Program relevance for livelihood/project monitoring |
| Fisher-folks | FF | 145 | Program relevance for LAWA and related activities |
| Out-of-School Youth | OSY | 33 | For targeted interventions |

**Monitoring note:** Sectoral counts are not necessarily mutually exclusive. Reports should state whether counts are unique beneficiaries or indicator totals.

## D.6 Report by LGU and Barangay

**Table D-4. Sample location-disaggregated validation report, sanitized.**

| Province | Municipality / City | Barangay | Target partner-beneficiaries | Imported partner-beneficiaries | Variance | Validation status |
| --- | --- | --- | ---: | ---: | ---: | --- |
| PROV-01 | LGU-01 | BRGY-001 | 120 | 120 | 0 | Validated |
| PROV-01 | LGU-01 | BRGY-002 | 100 | 92 | -8 | Partial |
| PROV-01 | LGU-02 | BRGY-003 | 80 | 87 | 7 | Over Target |
| PROV-02 | LGU-03 | BRGY-004 | 0 | 12 | 12 | Unplanned Import |

**Decision-use note:** Location-level validation highlights areas requiring correction, target review, or additional documentation before final submission.

## D.7 Report by Program / Activity

**Table D-5. Sample implementation status by program/activity, sanitized.**

| Fiscal year | Program classification | Project/activity type | Target | Actual accomplishment | Accomplishment rate | Remarks |
| --- | --- | --- | ---: | ---: | ---: | --- |
| 2026 | LAWA | Construction / installation | 25 | 20 | 80.00% | Ongoing implementation |
| 2026 | LAWA | Rehabilitation | 12 | 12 | 100.00% | Completed |
| 2026 | BINHI | Communal garden | 35 | 30 | 85.71% | Pending final validation |
| 2026 | BINHI | Nursery / seedling support | 18 | 15 | 83.33% | For monitoring |

**Decision-use note:** Program/activity reports support progress tracking, bottleneck identification, resource allocation, and preparation of management briefs.

## D.8 Report by Implementation Status

**Table D-6. Sample implementation status summary, sanitized.**

| Status | Number of project/activity entries | Percent share | Management note |
| --- | ---: | ---: | --- |
| Not started | 8 | 10.00% | Requires schedule follow-up |
| Ongoing | 46 | 57.50% | Monitor implementation milestones |
| Completed | 22 | 27.50% | Validate supporting evidence |
| For correction | 4 | 5.00% | Resolve data or documentation issue |
| **Total** | **80** | **100.00%** |  |

## D.9 Report by Validation Status

**Table D-7. Sample MEB validation status summary, sanitized.**

| Validation status | Location count | Priority action |
| --- | ---: | --- |
| Validated | 42 | Include in final validated output |
| Partial | 9 | Review missing imports or target adjustment |
| Over Target | 4 | Review excess rows and source documentation |
| No Import | 5 | Follow up with responsible uploader/LGU |
| No Target | 3 | Confirm whether location should be covered |
| Unplanned Import | 2 | Validate target encoding or remove incorrect import |

## D.10 Report Use in Monitoring and Decision-Making

The above reports support:

- Regular review of target-versus-actual implementation status.
- Identification of geographic areas requiring follow-up or correction.
- Monitoring of beneficiary profile coverage by sex, age, disability, and sectoral classification.
- Prioritization of validation, deduplication, and crossmatch exceptions.
- Preparation of evidence-based management updates and GPD/KM submission materials.

## D.11 Owner Validation Notes

- Synthetic sample figures are retained for documentation format only; approved aggregate report extracts may replace them in the controlled submission copy when authorized.
- The sample reporting period is aligned to the 2026 repository review context; the official reporting period, fiscal year, and program coverage should be stated in the final transmittal or report header.
- Sectoral counts should be treated as multi-indicator counts unless the exported report explicitly states that the figures are deduplicated unique-beneficiary counts.
- <span style="color:red">[MANUAL INPUT REQUIRED]</span> Insert screenshot references from Annex A and official signatory block.
