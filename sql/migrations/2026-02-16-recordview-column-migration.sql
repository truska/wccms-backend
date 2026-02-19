-- Name: recordView column Migration
-- Backfill legacy cms_form col* view definitions into cms_form_view_list.
-- Idempotent: skips rows already present by form_id + sort + name.

INSERT INTO cms_form_view_list (
  form_id,
  name,
  overridename,
  tableID,
  type,
  ruleid,
  sort,
  showonweb,
  archived
)
SELECT
  x.form_id,
  x.name,
  x.overridename,
  x.tableID,
  x.type,
  x.ruleid,
  x.sort,
  x.showonweb,
  x.archived
FROM (
  SELECT
    f.id AS form_id,
    TRIM(f.col1) AS name,
    NULLIF(TRIM(f.col1name), '') AS overridename,
    CAST(COALESCE(NULLIF(TRIM(f.col1table), ''), '0') AS UNSIGNED) AS tableID,
    CASE WHEN f.col1type IN ('None','Search','Select') THEN f.col1type ELSE 'Search' END AS type,
    COALESCE(NULLIF(f.col1datatype, 0), 1) AS ruleid,
    1 AS sort,
    CASE WHEN f.showonweb = 'No' THEN 'No' ELSE 'Yes' END AS showonweb,
    CASE WHEN f.archived = 1 THEN 1 ELSE 0 END AS archived
  FROM cms_form f

  UNION ALL

  SELECT
    f.id,
    TRIM(f.col2),
    NULLIF(TRIM(f.col2name), ''),
    CAST(COALESCE(NULLIF(TRIM(f.col2table), ''), '0') AS UNSIGNED),
    CASE WHEN f.col2type IN ('None','Search','Select') THEN f.col2type ELSE 'Search' END,
    COALESCE(NULLIF(f.col2datatype, 0), 1),
    2,
    CASE WHEN f.showonweb = 'No' THEN 'No' ELSE 'Yes' END,
    CASE WHEN f.archived = 1 THEN 1 ELSE 0 END
  FROM cms_form f

  UNION ALL

  SELECT
    f.id,
    TRIM(f.col3),
    NULLIF(TRIM(f.col3name), ''),
    CAST(COALESCE(NULLIF(TRIM(f.col3table), ''), '0') AS UNSIGNED),
    CASE WHEN f.col3type IN ('None','Search','Select') THEN f.col3type ELSE 'Search' END,
    COALESCE(NULLIF(f.col3datatype, 0), 1),
    3,
    CASE WHEN f.showonweb = 'No' THEN 'No' ELSE 'Yes' END,
    CASE WHEN f.archived = 1 THEN 1 ELSE 0 END
  FROM cms_form f

  UNION ALL

  SELECT
    f.id,
    TRIM(f.col4),
    NULLIF(TRIM(f.col4name), ''),
    CAST(COALESCE(NULLIF(TRIM(f.col4table), ''), '0') AS UNSIGNED),
    CASE WHEN f.col4type IN ('None','Search','Select') THEN f.col4type ELSE 'Search' END,
    COALESCE(NULLIF(f.col4datatype, 0), 1),
    4,
    CASE WHEN f.showonweb = 'No' THEN 'No' ELSE 'Yes' END,
    CASE WHEN f.archived = 1 THEN 1 ELSE 0 END
  FROM cms_form f

  UNION ALL

  SELECT
    f.id,
    TRIM(f.col5),
    NULLIF(TRIM(f.col5name), ''),
    CAST(COALESCE(NULLIF(TRIM(f.col5table), ''), '0') AS UNSIGNED),
    CASE WHEN f.col5type IN ('None','Search','Select') THEN f.col5type ELSE 'Search' END,
    COALESCE(NULLIF(f.col5datatype, 0), 1),
    5,
    CASE WHEN f.showonweb = 'No' THEN 'No' ELSE 'Yes' END,
    CASE WHEN f.archived = 1 THEN 1 ELSE 0 END
  FROM cms_form f

  UNION ALL

  SELECT
    f.id,
    TRIM(f.col6),
    NULLIF(TRIM(f.col6name), ''),
    CAST(COALESCE(NULLIF(TRIM(f.col6table), ''), '0') AS UNSIGNED),
    CASE WHEN f.col6type IN ('None','Search','Select') THEN f.col6type ELSE 'Search' END,
    COALESCE(NULLIF(f.col6datatype, 0), 1),
    6,
    CASE WHEN f.showonweb = 'No' THEN 'No' ELSE 'Yes' END,
    CASE WHEN f.archived = 1 THEN 1 ELSE 0 END
  FROM cms_form f
) x
WHERE
  x.name IS NOT NULL
  AND x.name <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM cms_form_view_list v
    WHERE v.form_id = x.form_id
      AND v.sort = x.sort
      AND v.name = x.name
  );
