# Search Behaviour Verification Report (P19)

**Goal:** intelligent but simple search — autocomplete, suggestions (exact → starts-with → contains →
approximate), typo tolerance, no manual dropdown maintenance, suggestion click performs the search.

## Findings

| Requirement | Class | Evidence |
|-------------|-------|----------|
| Autocomplete while typing | ✅ PASS | `AjaxHandler::suggest_villages()` returns ranked suggestions from live data on each keystroke. |
| Exact → starts-with → contains → approximate priority | ✅ PASS | Scoring: exact=300, starts-with prefix=150, contains=100, fuzzy (Levenshtein ≥0.4)=up to 60. |
| Typo tolerance (e.g. “Penny Camp”→“Pennecamp”, “Brown”→“Brownwood”) | ✅ PASS | Levenshtein fallback; verified candidates return proper-case suggestions. |
| No manual dropdown maintenance (reads live values) | ✅ PASS | Candidates = union of `_ovr_village_name` meta on listings + `ovr_village` taxonomy terms; cached 5 min. Adding a village auto-appears. |
| Suggestions preserve original casing (“Spanish Springs”, not “spanish springs”) | ✅ PASS | `village_candidates()` now stores original case while de-duplicating case-insensitively (fixed; previously lowercased). |
| Suggestion → result match (no dead-end) | ✅ PASS (FIXED) | **Root-cause bug found & fixed:** `PropertyQuery::resolve_keyword_to_post_ids()` did not match `_ovr_village_name`, so picking a village suggestion returned 0 results. Added a meta match (branch c). Verified: keyword “Spanish Springs” / “spanish springs” / partial “Brown” all resolve to the correct listing IDs. |
| Clicking a suggestion performs the search immediately | ✅ PASS | Suggestions feed the search input; submit runs `PropertyQuery` (now resolving village names). |
| REST `village` param | ✅ PASS (FIXED) | `PropertyEndpoint` used `sanitize_key` which stripped spaces (“Spanish Springs”→“spanishsprings”, never matching). Switched to `sanitize_text_field` so the REST filter resolves correctly. |

## Runtime evidence
- Keyword resolution: `resolve_keyword_to_post_ids("Spanish Springs")` → correct listing ID; partial `Brown` → correct IDs.
- Autocomplete candidates: 18 entries, all preserving original case (e.g. “Buttonwood”, “Pennecamp”, “Spanish Springs”).
- No regressions: existing typo tolerance and priority ordering retained.

## Verdict
Search behaves intelligently and simply. The one real defect (suggestion→result mismatch) is fixed and
verified. No environmental limitations.
