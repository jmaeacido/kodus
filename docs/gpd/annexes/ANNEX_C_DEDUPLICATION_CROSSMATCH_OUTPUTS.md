# Annex C - Deduplication and Crossmatch Outputs

**Document status:** Draft for data-owner validation  
**System:** KliMalasakit Operational Data Unified System (KODUS)  
**Scope:** Sanitized examples of deduplication outputs, name-matching results, crossmatch summaries, match confidence/statuses, and exception handling  
**Privacy note:** All names, locations, dates, and row identifiers below are synthetic. Do not replace these examples with raw beneficiary records unless fully redacted and formally approved.

## C.1 Purpose

This annex provides submission-ready sample structures for documenting how KODUS supports duplicate detection, name matching, and crossmatching of beneficiary datasets. The examples are designed for review and reporting without exposing personal information.

## C.2 Deduplication Required Fields

The current deduplication parser maps required columns using accepted aliases for:

| Canonical field | Example accepted labels |
| --- | --- |
| `lastName` | lastname, last, lname |
| `firstName` | firstname, first, fname |
| `middleName` | middlename, middle, mname |
| `ext` | ext, suffix |
| `birthDate` | birthdate, birth, dob, dateofbirth |
| `barangay` | barangay, brgy |
| `lgu` | lgu, city, municipality |
| `province` | province, prov |

## C.3 Sanitized Deduplication Output

**Table C-1. Sample duplicate group output, sanitized.**

| Job no. | Duplicate group | Synthetic row reference | Sanitized name key | Birthdate key | Location key | Similarity | Reviewer status |
| --- | ---: | --- | --- | --- | --- | ---: | --- |
| DEDUP-2026-001 | 1 | ROW-A001 | PERSON ALPHA | 1985-XX-XX | BRGY-01 / LGU-01 / PROV-01 | 100.00% | Confirmed duplicate |
| DEDUP-2026-001 | 1 | ROW-A014 | PERSON ALPHA | 1985-XX-XX | BRGY-01 / LGU-01 / PROV-01 | 98.75% | Confirmed duplicate |
| DEDUP-2026-001 | 2 | ROW-B003 | PERSON BETA | 1979-XX-XX | BRGY-04 / LGU-03 / PROV-02 | 92.40% | For field verification |
| DEDUP-2026-001 | 2 | ROW-B022 | PERSON BETA VARIANT | 1979-XX-XX | BRGY-04 / LGU-03 / PROV-02 | 91.10% | For field verification |

**Interpretation note:** Similarity values indicate possible duplicate relationships. The final action must be based on reviewer validation and official source documents, not on the score alone.

## C.4 Deduplication Summary

**Table C-2. Sample deduplication summary, sanitized.**

| Metric | Count | Note |
| --- | ---: | --- |
| Uploaded records | 1,250 | Synthetic count for documentation format only |
| Valid parsed records | 1,246 | Excludes blank or invalid rows |
| Duplicate groups detected | 18 | Groups requiring reviewer action |
| Records inside duplicate groups | 42 | Includes lead and possible duplicate rows |
| Confirmed duplicates | 16 | Confirmed through manual review |
| For field verification | 22 | Pending LGU/program focal confirmation |
| No action / false positive | 4 | Similarity did not represent same beneficiary |

Synthetic counts are retained for the annex sample format. If the signed submission requires operational figures, use approved aggregate counts only and exclude raw beneficiary rows.

## C.5 Crossmatch Scoring Structure

KODUS crossmatch scoring uses name, birthdate, and address components. The current helper applies weighted score components, with default weights observed in the code as name 60%, birthdate 20%, and address 20%, subject to configured options.

**Table C-3. Sample crossmatch output, sanitized.**

| Uploaded record reference | Candidate rank | Candidate reference | Name score | Birth score | Address score | Overall score | Suggested status |
| --- | ---: | --- | ---: | ---: | ---: | ---: | --- |
| UP-0001 | 1 | MEB-CAND-0104 | 96.25% | 100.00% | 93.30% | 96.25% | Probable match |
| UP-0001 | 2 | MEB-CAND-0342 | 86.10% | 100.00% | 80.00% | 87.66% | Possible match |
| UP-0002 | 1 | MEB-CAND-0711 | 74.00% | 0.00% | 90.00% | 62.40% | Below threshold / review only |
| UP-0003 | - | No candidate | - | - | - | - | No match found |

**Interpretation note:** High name and address similarity may still require further review when birthdate is missing or inconsistent. A low or missing birthdate score does not automatically disprove identity where source records are incomplete.

## C.6 Name-Matching / MEBIS Output Summary

**Table C-4. Sample name-matching summary, sanitized.**

| Output batch | Province code | Municipality/city code | Input rows | Matched rows | Unmatched rows | Exceptions | Remarks |
| --- | --- | --- | ---: | ---: | ---: | ---: | --- |
| NM-2026-001 | PROV-01 | LGU-01 | 320 | 301 | 12 | 7 | For reviewer validation |
| NM-2026-001 | PROV-01 | LGU-02 | 285 | 270 | 9 | 6 | Minor spelling variants noted |
| NM-2026-001 | PROV-02 | LGU-03 | 410 | 388 | 15 | 7 | Requires external confirmation |

The annex uses neutral documentation labels for MEBIS/name-matching outputs. If an operational export uses different configured labels, the signed submission should align the label names with the approved export while preserving the same reviewer-action meaning.

## C.7 Match Confidence and Review Statuses

**Table C-5. Recommended documentation labels for match review.**

| Label | Recommended meaning | Required action |
| --- | --- | --- |
| Confirmed duplicate | Reviewer confirms same beneficiary appears more than once. | Retain official record according to SOP; document correction. |
| Probable match | Score and supporting fields strongly indicate same person. | Review source documents and confirm before action. |
| Possible match | Some fields match, but evidence is incomplete or mixed. | Field or program focal verification required. |
| Below threshold | Score is lower than the operational threshold. | No action unless separately flagged by reviewer. |
| No candidate | No match candidate returned by the system. | No duplicate/crossmatch action required from this job. |
| Exception | Data is incomplete, malformed, or requires manual handling. | Correct source data or document exception. |

## C.8 Exception Handling Log

**Table C-6. Sanitized exception log format.**

| Exception no. | Source | Synthetic row reference | Issue type | Action taken | Responsible unit | Date resolved |
| --- | --- | --- | --- | --- | --- | --- |
| EX-001 | Deduplication | ROW-X010 | Missing birthdate | Returned to source office for correction | MEB validation reviewer / source office focal | 2026-05-12 sample |
| EX-002 | Crossmatch | UP-0041 | Conflicting barangay | Validated against approved LGU list | Program focal / LGU validation focal | 2026-05-12 sample |
| EX-003 | Name matching | NM-0199 | Suffix mismatch | Marked for reviewer confirmation | MEBIS reviewer / data-quality focal | 2026-05-12 sample |

## C.9 Data Protection Controls

- Use synthetic row references instead of beneficiary IDs in annexes.
- Mask exact birthdates unless the data owner approves their inclusion.
- Show aggregate location labels or coded locations where possible.
- Do not include uploaded filenames that contain beneficiary names or confidential batch identifiers.
- Store full outputs only in authorized KODUS locations with approved retention and access controls.

## C.10 Owner Validation Notes

- Operational score thresholds for deduplication and crossmatch should follow the configured job settings for the reviewed run and remain subject to reviewer validation.
- Reviewer status labels should preserve the meanings in Table C-5 unless the controlled export uses approved alternative label names.
- <span style="color:red">[MANUAL INPUT REQUIRED]</span> Insert sanitized screenshot references from Annex A after capture.
- Aggregate summaries may be used in submission copies when approved by the data owner; detailed outputs and raw candidate records remain internal controlled records.
