<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_20260126_04 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fx_resolve_rate(as_of_date, from_currency_code, to_currency_code, max_depth) function with 7-day fallback, EUR/USD pivots, and recursive path search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE OR REPLACE FUNCTION app.fx_resolve_rate(
    p_as_of_date date,
    p_from_currency_code char(3),
    p_to_currency_code char(3),
    p_max_depth integer DEFAULT 5
)
RETURNS TABLE(
    rate numeric(18, 8),
    fx_rate_date date,
    path text
)
LANGUAGE plpgsql
AS $$
DECLARE
    resolvedRate numeric;
    resolvedFxDate date;
    resolvedPath text;

    leg1Rate numeric;
    leg1Date date;
    leg2Rate numeric;
    leg2Date date;

    pivotCurrencyCode char(3);
BEGIN
    -- Basic sanity
    IF p_as_of_date IS NULL THEN
        RAISE EXCEPTION 'as_of_date must not be null';
    END IF;

    IF p_from_currency_code IS NULL OR p_to_currency_code IS NULL THEN
        RAISE EXCEPTION 'from_currency_code and to_currency_code must not be null';
    END IF;

    IF p_max_depth IS NULL OR p_max_depth < 1 THEN
        p_max_depth := 1;
    END IF;

    -- Same currency: identity conversion
    IF btrim(p_from_currency_code) = btrim(p_to_currency_code) THEN
        rate := 1.00000000;
        fx_rate_date := p_as_of_date;
        path := btrim(p_from_currency_code);
        RETURN NEXT;
        RETURN;
    END IF;

    -------------------------------------------------------------------------
    -- Fast path: direct (from -> to), best available <= as_of_date within 7 days
    -------------------------------------------------------------------------
    SELECT r.rate, r.rate_date
    INTO leg1Rate, leg1Date
    FROM app.tbl_fx_rate r
    WHERE r.from_currency_code = p_from_currency_code
      AND r.to_currency_code = p_to_currency_code
      AND r.rate_date <= p_as_of_date
      AND r.rate_date >= (p_as_of_date - 7)
    ORDER BY r.rate_date DESC
    LIMIT 1;

    IF leg1Rate IS NOT NULL THEN
        rate := leg1Rate;
        fx_rate_date := leg1Date;
        path := btrim(p_from_currency_code) || '->' || btrim(p_to_currency_code);
        RETURN NEXT;
        RETURN;
    END IF;

    -------------------------------------------------------------------------
    -- Fast path: reverse (to -> from), best available <= as_of_date within 7 days
    -- Result is 1/rate
    -------------------------------------------------------------------------
    SELECT r.rate, r.rate_date
    INTO leg1Rate, leg1Date
    FROM app.tbl_fx_rate r
    WHERE r.from_currency_code = p_to_currency_code
      AND r.to_currency_code = p_from_currency_code
      AND r.rate_date <= p_as_of_date
      AND r.rate_date >= (p_as_of_date - 7)
    ORDER BY r.rate_date DESC
    LIMIT 1;

    IF leg1Rate IS NOT NULL THEN
        rate := (1.0::numeric / leg1Rate);
        fx_rate_date := leg1Date;
        path := btrim(p_from_currency_code) || '->' || btrim(p_to_currency_code) || ' (reverse)';
        RETURN NEXT;
        RETURN;
    END IF;

    -------------------------------------------------------------------------
    -- Pivot attempts: EUR then USD (each leg uses best-available within 7 days)
    -- Each leg can be direct or reverse. fx_rate_date is the oldest leg date.
    -------------------------------------------------------------------------
    FOREACH pivotCurrencyCode IN ARRAY ARRAY['EUR'::char(3), 'USD'::char(3)]
    LOOP
        leg1Rate := NULL;
        leg1Date := NULL;
        leg2Rate := NULL;
        leg2Date := NULL;

        -- Leg1: from -> pivot (direct)
        SELECT r.rate, r.rate_date
        INTO leg1Rate, leg1Date
        FROM app.tbl_fx_rate r
        WHERE r.from_currency_code = p_from_currency_code
          AND r.to_currency_code = pivotCurrencyCode
          AND r.rate_date <= p_as_of_date
          AND r.rate_date >= (p_as_of_date - 7)
        ORDER BY r.rate_date DESC
        LIMIT 1;

        -- Leg1: from -> pivot (reverse)
        IF leg1Rate IS NULL THEN
            SELECT r.rate, r.rate_date
            INTO leg1Rate, leg1Date
            FROM app.tbl_fx_rate r
            WHERE r.from_currency_code = pivotCurrencyCode
              AND r.to_currency_code = p_from_currency_code
              AND r.rate_date <= p_as_of_date
              AND r.rate_date >= (p_as_of_date - 7)
            ORDER BY r.rate_date DESC
            LIMIT 1;

            IF leg1Rate IS NOT NULL THEN
                leg1Rate := (1.0::numeric / leg1Rate);
            END IF;
        END IF;

        IF leg1Rate IS NULL THEN
            CONTINUE;
        END IF;

        -- Leg2: pivot -> to (direct)
        SELECT r.rate, r.rate_date
        INTO leg2Rate, leg2Date
        FROM app.tbl_fx_rate r
        WHERE r.from_currency_code = pivotCurrencyCode
          AND r.to_currency_code = p_to_currency_code
          AND r.rate_date <= p_as_of_date
          AND r.rate_date >= (p_as_of_date - 7)
        ORDER BY r.rate_date DESC
        LIMIT 1;

        -- Leg2: pivot -> to (reverse)
        IF leg2Rate IS NULL THEN
            SELECT r.rate, r.rate_date
            INTO leg2Rate, leg2Date
            FROM app.tbl_fx_rate r
            WHERE r.from_currency_code = p_to_currency_code
              AND r.to_currency_code = pivotCurrencyCode
              AND r.rate_date <= p_as_of_date
              AND r.rate_date >= (p_as_of_date - 7)
            ORDER BY r.rate_date DESC
            LIMIT 1;

            IF leg2Rate IS NOT NULL THEN
                leg2Rate := (1.0::numeric / leg2Rate);
            END IF;
        END IF;

        IF leg2Rate IS NULL THEN
            CONTINUE;
        END IF;

        rate := round((leg1Rate * leg2Rate), 8);
        fx_rate_date := LEAST(leg1Date, leg2Date);
        path := btrim(p_from_currency_code) || '->' || btrim(pivotCurrencyCode) || '->' || btrim(p_to_currency_code);
        RETURN NEXT;
        RETURN;
    END LOOP;

    -------------------------------------------------------------------------
    -- Recursive path search (max_depth), using "effective edges" per (from,to)
    -- Edge selection: for each pair pick the latest rate_date <= as_of_date
    -- within the last 7 days. Reverse edges are added as 1/rate.
    -- Each path returns fx_rate_date = MIN(edge.rate_date) (oldest edge used).
    -------------------------------------------------------------------------
    WITH RECURSIVE effective_direct AS (
        SELECT DISTINCT ON (r.from_currency_code, r.to_currency_code)
            r.from_currency_code,
            r.to_currency_code,
            r.rate,
            r.rate_date
        FROM app.tbl_fx_rate r
        WHERE r.rate_date <= p_as_of_date
          AND r.rate_date >= (p_as_of_date - 7)
        ORDER BY r.from_currency_code, r.to_currency_code, r.rate_date DESC
    ),
    edges AS (
        SELECT
            d.from_currency_code AS from_code,
            d.to_currency_code AS to_code,
            d.rate AS rate,
            d.rate_date AS rate_date
        FROM effective_direct d

        UNION ALL

        SELECT
            d.to_currency_code AS from_code,
            d.from_currency_code AS to_code,
            (1.0::numeric / d.rate) AS rate,
            d.rate_date AS rate_date
        FROM effective_direct d
    ),
    search AS (
        SELECT
            p_from_currency_code AS current_code,
            0 AS depth,
            1.0::numeric AS accum_rate,
            p_as_of_date AS min_rate_date,
            ARRAY[btrim(p_from_currency_code)]::text[] AS visited,
            btrim(p_from_currency_code) AS path_text
        UNION ALL
        SELECT
            e.to_code AS current_code,
            s.depth + 1 AS depth,
            (s.accum_rate * e.rate) AS accum_rate,
            LEAST(s.min_rate_date, e.rate_date) AS min_rate_date,
            (s.visited || btrim(e.to_code))::text[] AS visited,
            (s.path_text || '->' || btrim(e.to_code)) AS path_text
        FROM search s
        JOIN edges e
            ON e.from_code = s.current_code
        WHERE s.depth < p_max_depth
          AND NOT (btrim(e.to_code) = ANY (s.visited))
    ),
    candidates AS (
        SELECT
            round(accum_rate, 8) AS candidate_rate,
            min_rate_date AS candidate_fx_date,
            path_text AS candidate_path,
            depth AS candidate_depth
        FROM search
        WHERE btrim(current_code) = btrim(p_to_currency_code)
          AND depth > 0
    )
    SELECT
        c.candidate_rate,
        c.candidate_fx_date,
        c.candidate_path
    INTO
        resolvedRate,
        resolvedFxDate,
        resolvedPath
    FROM candidates c
    ORDER BY
        c.candidate_depth ASC,
        c.candidate_fx_date DESC,
        c.candidate_path ASC
    LIMIT 1;

    IF resolvedRate IS NULL THEN
        RETURN;
    END IF;

    rate := resolvedRate;
    fx_rate_date := resolvedFxDate;
    path := resolvedPath;
    RETURN NEXT;
END;
$$
SQL);
    }
}
