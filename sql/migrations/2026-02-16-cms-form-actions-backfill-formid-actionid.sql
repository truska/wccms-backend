-- Backfill cms_form_actions new FK columns from legacy columns where missing.
-- Idempotent: only updates rows with NULL/0 target values.

UPDATE cms_form_actions
SET form_id = form
WHERE (form_id IS NULL OR form_id = 0)
  AND form IS NOT NULL
  AND form <> 0;

UPDATE cms_form_actions
SET action_id = action
WHERE (action_id IS NULL OR action_id = 0)
  AND action IS NOT NULL
  AND action <> 0;
