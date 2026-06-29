-- ==============================================================================
-- AmISafe H3 Analytics Stored Procedures
-- Calculates advanced analytics columns for amisafe_h3_aggregated table
-- ==============================================================================

DELIMITER $$

-- ==============================================================================
-- 1. Calculate Top Crime Type for a Hexagon
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_top_crime_type$$
CREATE PROCEDURE sp_calculate_top_crime_type(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    OUT p_top_crime_type VARCHAR(10)
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    SET h3_col = CONCAT('h3_res_', p_resolution);
    
    SET @query = CONCAT('
        SELECT ucr_general INTO @top_crime
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ? 
            AND is_duplicate = FALSE 
            AND incident_datetime IS NOT NULL
            AND ucr_general IS NOT NULL
        GROUP BY ucr_general
        ORDER BY COUNT(*) DESC
        LIMIT 1
    ');
    
    PREPARE stmt FROM @query;
    SET @h3_idx = p_h3_index;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    
    SET p_top_crime_type = @top_crime;
END$$

-- ==============================================================================
-- 2. Calculate Crime Diversity Index (Shannon Index)
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_crime_diversity$$
CREATE PROCEDURE sp_calculate_crime_diversity(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    OUT p_diversity_index DECIMAL(10,3)
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    DECLARE total_crimes INT;
    DECLARE diversity DECIMAL(10,3) DEFAULT 0.0;
    
    SET h3_col = CONCAT('h3_res_', p_resolution);
    
    -- Get total crime count
    SET @count_query = CONCAT('
        SELECT COUNT(*) INTO @total
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ? 
            AND is_duplicate = FALSE 
            AND incident_datetime IS NOT NULL
    ');
    
    PREPARE stmt FROM @count_query;
    SET @h3_idx = p_h3_index;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    
    SET total_crimes = @total;
    
    IF total_crimes > 1 THEN
        -- Calculate Shannon diversity: -SUM(p * ln(p))
        SET @diversity_query = CONCAT('
            SELECT -SUM((cnt / ?) * LN(cnt / ?)) INTO @div
            FROM (
                SELECT COUNT(*) as cnt
                FROM amisafe_clean_incidents
                WHERE ', h3_col, ' = ? 
                    AND is_duplicate = FALSE 
                    AND incident_datetime IS NOT NULL
                    AND ucr_general IS NOT NULL
                GROUP BY ucr_general
            ) crime_counts
        ');
        
        PREPARE stmt FROM @diversity_query;
        SET @total_f = CAST(total_crimes AS DECIMAL(10,2));
        EXECUTE stmt USING @total_f, @total_f, @h3_idx;
        DEALLOCATE PREPARE stmt;
        
        SET diversity = @div;
    END IF;
    
    SET p_diversity_index = diversity;
END$$

-- ==============================================================================
-- 3. Calculate Temporal Patterns (Hour, Day of Week, Month)
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_temporal_patterns$$
CREATE PROCEDURE sp_calculate_temporal_patterns(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    OUT p_by_hour JSON,
    OUT p_by_dow JSON,
    OUT p_by_month JSON,
    OUT p_peak_hour INT,
    OUT p_peak_dow INT
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    SET h3_col = CONCAT('h3_res_', p_resolution);
    
    -- Hourly distribution
    SET @hour_query = CONCAT('
        SELECT JSON_ARRAYAGG(IFNULL(cnt, 0)) INTO @hourly
        FROM (
            SELECT h, IFNULL(hour_counts.cnt, 0) as cnt
            FROM (
                SELECT 0 AS h UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION 
                SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION 
                SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION 
                SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION 
                SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION 
                SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23
            ) hours
            LEFT JOIN (
                SELECT HOUR(incident_datetime) as hour, COUNT(*) as cnt
                FROM amisafe_clean_incidents
                WHERE ', h3_col, ' = ? 
                    AND is_duplicate = FALSE 
                    AND incident_datetime IS NOT NULL
                GROUP BY HOUR(incident_datetime)
            ) hour_counts ON hours.h = hour_counts.hour
            ORDER BY h
        ) hourly_data
    ');
    
    PREPARE stmt FROM @hour_query;
    SET @h3_idx = p_h3_index;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    SET p_by_hour = @hourly;
    
    -- Day of week distribution
    SET @dow_query = CONCAT('
        SELECT JSON_ARRAYAGG(IFNULL(cnt, 0)) INTO @daily
        FROM (
            SELECT d, IFNULL(dow_counts.cnt, 0) as cnt
            FROM (
                SELECT 0 AS d UNION SELECT 1 UNION SELECT 2 UNION 
                SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
            ) days
            LEFT JOIN (
                SELECT WEEKDAY(incident_datetime) as dow, COUNT(*) as cnt
                FROM amisafe_clean_incidents
                WHERE ', h3_col, ' = ? 
                    AND is_duplicate = FALSE 
                    AND incident_datetime IS NOT NULL
                GROUP BY WEEKDAY(incident_datetime)
            ) dow_counts ON days.d = dow_counts.dow
            ORDER BY d
        ) dow_data
    ');
    
    PREPARE stmt FROM @dow_query;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    SET p_by_dow = @daily;
    
    -- Monthly distribution
    SET @month_query = CONCAT('
        SELECT JSON_ARRAYAGG(IFNULL(cnt, 0)) INTO @monthly
        FROM (
            SELECT m, IFNULL(month_counts.cnt, 0) as cnt
            FROM (
                SELECT 1 AS m UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION 
                SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION 
                SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12
            ) months
            LEFT JOIN (
                SELECT MONTH(incident_datetime) as mon, COUNT(*) as cnt
                FROM amisafe_clean_incidents
                WHERE ', h3_col, ' = ? 
                    AND is_duplicate = FALSE 
                    AND incident_datetime IS NOT NULL
                GROUP BY MONTH(incident_datetime)
            ) month_counts ON months.m = month_counts.mon
            ORDER BY m
        ) month_data
    ');
    
    PREPARE stmt FROM @month_query;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    SET p_by_month = @monthly;
    
    -- Peak hour
    SET @peak_hour_query = CONCAT('
        SELECT HOUR(incident_datetime) INTO @ph
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ? 
            AND is_duplicate = FALSE 
            AND incident_datetime IS NOT NULL
        GROUP BY HOUR(incident_datetime)
        ORDER BY COUNT(*) DESC
        LIMIT 1
    ');
    
    PREPARE stmt FROM @peak_hour_query;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    SET p_peak_hour = @ph;
    
    -- Peak day of week
    SET @peak_dow_query = CONCAT('
        SELECT WEEKDAY(incident_datetime) INTO @pd
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ? 
            AND is_duplicate = FALSE 
            AND incident_datetime IS NOT NULL
        GROUP BY WEEKDAY(incident_datetime)
        ORDER BY COUNT(*) DESC
        LIMIT 1
    ');
    
    PREPARE stmt FROM @peak_dow_query;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    SET p_peak_dow = @pd;
END$$

-- ==============================================================================
-- 4. Calculate Violent vs Non-Violent Crime Counts
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_violent_stats$$
CREATE PROCEDURE sp_calculate_violent_stats(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    OUT p_violent_count INT,
    OUT p_nonviolent_count INT,
    OUT p_violent_pct DECIMAL(5,2)
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    DECLARE total_count INT;
    
    SET h3_col = CONCAT('h3_res_', p_resolution);
    
    -- Violent crimes: UCR codes 100, 200, 300, 400
    SET @violent_query = CONCAT('
        SELECT 
            SUM(CASE WHEN ucr_general IN (''100'', ''200'', ''300'', ''400'') THEN 1 ELSE 0 END),
            SUM(CASE WHEN ucr_general NOT IN (''100'', ''200'', ''300'', ''400'') OR ucr_general IS NULL THEN 1 ELSE 0 END),
            COUNT(*)
        INTO @violent, @nonviolent, @total
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ? 
            AND is_duplicate = FALSE 
            AND incident_datetime IS NOT NULL
    ');
    
    PREPARE stmt FROM @violent_query;
    SET @h3_idx = p_h3_index;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    
    SET p_violent_count = IFNULL(@violent, 0);
    SET p_nonviolent_count = IFNULL(@nonviolent, 0);
    SET total_count = IFNULL(@total, 0);
    
    IF total_count > 0 THEN
        SET p_violent_pct = (p_violent_count / total_count) * 100;
    ELSE
        SET p_violent_pct = 0.0;
    END IF;
END$$

-- ==============================================================================
-- 5. Calculate Crime Type and District Counts (JSON)
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_crime_district_counts$$
CREATE PROCEDURE sp_calculate_crime_district_counts(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    OUT p_crime_counts JSON,
    OUT p_district_counts JSON
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    SET h3_col = CONCAT('h3_res_', p_resolution);
    
    -- Crime type counts
    SET @crime_query = CONCAT('
        SELECT JSON_OBJECTAGG(ucr_general, cnt) INTO @crime_json
        FROM (
            SELECT ucr_general, COUNT(*) as cnt
            FROM amisafe_clean_incidents
            WHERE ', h3_col, ' = ? 
                AND is_duplicate = FALSE 
                AND incident_datetime IS NOT NULL
                AND ucr_general IS NOT NULL
            GROUP BY ucr_general
        ) crime_data
    ');
    
    PREPARE stmt FROM @crime_query;
    SET @h3_idx = p_h3_index;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    SET p_crime_counts = IFNULL(@crime_json, JSON_OBJECT());
    
    -- District counts
    SET @district_query = CONCAT('
        SELECT JSON_OBJECTAGG(dc_dist, cnt) INTO @district_json
        FROM (
            SELECT CAST(dc_dist AS CHAR) as dc_dist, COUNT(*) as cnt
            FROM amisafe_clean_incidents
            WHERE ', h3_col, ' = ? 
                AND is_duplicate = FALSE 
                AND incident_datetime IS NOT NULL
                AND dc_dist IS NOT NULL
            GROUP BY dc_dist
        ) district_data
    ');
    
    PREPARE stmt FROM @district_query;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    SET p_district_counts = IFNULL(@district_json, JSON_OBJECT());
END$$

-- ==============================================================================
-- 6. Calculate Date Range and Freshness
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_date_freshness$$
CREATE PROCEDURE sp_calculate_date_freshness(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    OUT p_date_start DATE,
    OUT p_date_end DATE,
    OUT p_freshness_days INT
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    SET h3_col = CONCAT('h3_res_', p_resolution);
    
    SET @date_query = CONCAT('
        SELECT 
            MIN(DATE(incident_datetime)),
            MAX(DATE(incident_datetime)),
            DATEDIFF(CURDATE(), MAX(DATE(incident_datetime)))
        INTO @start_date, @end_date, @freshness
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ? 
            AND is_duplicate = FALSE 
            AND incident_datetime IS NOT NULL
    ');
    
    PREPARE stmt FROM @date_query;
    SET @h3_idx = p_h3_index;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    
    SET p_date_start = @start_date;
    SET p_date_end = @end_date;
    SET p_freshness_days = @freshness;
END$$

-- ==============================================================================
-- 7. Master Procedure: Update All Analytics for One Hexagon
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_update_hex_analytics$$
CREATE PROCEDURE sp_update_hex_analytics(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT
)
BEGIN
    DECLARE v_top_crime VARCHAR(10);
    DECLARE v_diversity DECIMAL(10,3);
    DECLARE v_by_hour JSON;
    DECLARE v_by_dow JSON;
    DECLARE v_by_month JSON;
    DECLARE v_peak_hour INT;
    DECLARE v_peak_dow INT;
    DECLARE v_violent_count INT;
    DECLARE v_nonviolent_count INT;
    DECLARE v_violent_pct DECIMAL(5,2);
    DECLARE v_crime_counts JSON;
    DECLARE v_district_counts JSON;
    DECLARE v_date_start DATE;
    DECLARE v_date_end DATE;
    DECLARE v_freshness INT;
    
    -- Call all calculation procedures
    CALL sp_calculate_top_crime_type(p_h3_index, p_resolution, v_top_crime);
    CALL sp_calculate_crime_diversity(p_h3_index, p_resolution, v_diversity);
    CALL sp_calculate_temporal_patterns(p_h3_index, p_resolution, v_by_hour, v_by_dow, v_by_month, v_peak_hour, v_peak_dow);
    CALL sp_calculate_violent_stats(p_h3_index, p_resolution, v_violent_count, v_nonviolent_count, v_violent_pct);
    CALL sp_calculate_crime_district_counts(p_h3_index, p_resolution, v_crime_counts, v_district_counts);
    CALL sp_calculate_date_freshness(p_h3_index, p_resolution, v_date_start, v_date_end, v_freshness);
    
    -- Update the aggregated record
    UPDATE amisafe_h3_aggregated
    SET 
        top_crime_type = v_top_crime,
        crime_diversity_index = v_diversity,
        incidents_by_hour = v_by_hour,
        incidents_by_dow = v_by_dow,
        incidents_by_month = v_by_month,
        peak_hour = v_peak_hour,
        peak_dow = v_peak_dow,
        violent_crime_count = v_violent_count,
        nonviolent_crime_count = v_nonviolent_count,
        violent_crime_percentage = v_violent_pct,
        incident_type_counts = v_crime_counts,
        district_counts = v_district_counts,
        date_range_start = v_date_start,
        date_range_end = v_date_end,
        data_freshness_days = v_freshness,
        aggregation_batch_id = CONCAT('SQL_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'))
    WHERE h3_index = p_h3_index 
        AND h3_resolution = p_resolution;
END$$

-- ==============================================================================
-- 8. Batch Procedure: Update All Hexagons for a Resolution
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_update_resolution_analytics$$
CREATE PROCEDURE sp_update_resolution_analytics(
    IN p_resolution INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_h3_index VARCHAR(20);
    DECLARE v_counter INT DEFAULT 0;
    DECLARE v_total INT;
    
    DECLARE hex_cursor CURSOR FOR 
        SELECT h3_index 
        FROM amisafe_h3_aggregated 
        WHERE h3_resolution = p_resolution;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Get total count
    SELECT COUNT(*) INTO v_total
    FROM amisafe_h3_aggregated 
    WHERE h3_resolution = p_resolution;
    
    SELECT CONCAT('Starting analytics for resolution ', p_resolution, ': ', v_total, ' hexagons') AS status;
    
    OPEN hex_cursor;
    
    read_loop: LOOP
        FETCH hex_cursor INTO v_h3_index;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET v_counter = v_counter + 1;
        
        -- Process the hexagon
        CALL sp_update_hex_analytics(v_h3_index, p_resolution);
        
        -- Progress logging every 100 hexagons
        IF v_counter % 100 = 0 THEN
            SELECT CONCAT('Processed ', v_counter, '/', v_total, ' hexagons (', 
                   ROUND((v_counter/v_total)*100, 1), '%)') AS progress;
        END IF;
    END LOOP;
    
    CLOSE hex_cursor;
    
    SELECT CONCAT('Completed resolution ', p_resolution, ': ', v_counter, ' hexagons updated') AS result;
END$$

-- ==============================================================================
-- 9. Calculate Statistical Metrics (Z-Scores, Percentiles, Risk Scores)
-- OPTIMIZED: Uses temp table for O(n log n) percentile calculation instead of O(n²)
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_statistical_metrics$$
CREATE PROCEDURE sp_calculate_statistical_metrics(
    IN p_resolution INT
)
BEGIN
    -- Calculate population statistics (mean, std dev) for the resolution
    DECLARE v_violent_mean DECIMAL(10,2);
    DECLARE v_violent_stddev DECIMAL(10,2);
    DECLARE v_nonviolent_mean DECIMAL(10,2);
    DECLARE v_nonviolent_stddev DECIMAL(10,2);
    DECLARE v_incident_mean DECIMAL(10,2);
    DECLARE v_incident_stddev DECIMAL(10,2);
    DECLARE v_total_count INT;
    
    -- Get population statistics
    SELECT 
        AVG(violent_crime_count),
        STDDEV(violent_crime_count),
        AVG(nonviolent_crime_count),
        STDDEV(nonviolent_crime_count),
        AVG(incident_count),
        STDDEV(incident_count),
        COUNT(*)
    INTO 
        v_violent_mean, v_violent_stddev,
        v_nonviolent_mean, v_nonviolent_stddev,
        v_incident_mean, v_incident_stddev,
        v_total_count
    FROM amisafe_h3_aggregated
    WHERE h3_resolution = p_resolution;
    
    -- Step 1: Update z-scores only (fast)
    UPDATE amisafe_h3_aggregated
    SET 
        violent_crime_z_score = CASE 
            WHEN v_violent_stddev > 0 THEN (violent_crime_count - v_violent_mean) / v_violent_stddev
            ELSE 0 
        END,
        nonviolent_crime_z_score = CASE 
            WHEN v_nonviolent_stddev > 0 THEN (nonviolent_crime_count - v_nonviolent_mean) / v_nonviolent_stddev
            ELSE 0 
        END,
        incident_z_score = CASE 
            WHEN v_incident_stddev > 0 THEN (incident_count - v_incident_mean) / v_incident_stddev
            ELSE 0 
        END,
        violent_crime_mean = v_violent_mean,
        violent_crime_std_dev = v_violent_stddev,
        nonviolent_crime_mean = v_nonviolent_mean,
        nonviolent_crime_std_dev = v_nonviolent_stddev,
        incident_mean = v_incident_mean,
        incident_std_dev = v_incident_stddev
    WHERE h3_resolution = p_resolution;
    
    -- Step 2: Calculate percentiles using temp table with ranking
    -- This is O(n log n) instead of O(n²)
    
    DROP TEMPORARY TABLE IF EXISTS tmp_violent_ranks;
    CREATE TEMPORARY TABLE tmp_violent_ranks AS
    SELECT 
        h3_index,
        ROUND((@row_num := @row_num + 1) * 100.0 / v_total_count, 0) as percentile
    FROM amisafe_h3_aggregated, (SELECT @row_num := 0) r
    WHERE h3_resolution = p_resolution
    ORDER BY violent_crime_count ASC, h3_index ASC;
    
    UPDATE amisafe_h3_aggregated a
    INNER JOIN tmp_violent_ranks t ON a.h3_index = t.h3_index
    SET a.violent_crime_percentile = t.percentile
    WHERE a.h3_resolution = p_resolution;
    
    DROP TEMPORARY TABLE tmp_violent_ranks;
    
    -- Nonviolent percentiles
    DROP TEMPORARY TABLE IF EXISTS tmp_nonviolent_ranks;
    CREATE TEMPORARY TABLE tmp_nonviolent_ranks AS
    SELECT 
        h3_index,
        ROUND((@row_num := @row_num + 1) * 100.0 / v_total_count, 0) as percentile
    FROM amisafe_h3_aggregated, (SELECT @row_num := 0) r
    WHERE h3_resolution = p_resolution
    ORDER BY nonviolent_crime_count ASC, h3_index ASC;
    
    UPDATE amisafe_h3_aggregated a
    INNER JOIN tmp_nonviolent_ranks t ON a.h3_index = t.h3_index
    SET a.nonviolent_crime_percentile = t.percentile
    WHERE a.h3_resolution = p_resolution;
    
    DROP TEMPORARY TABLE tmp_nonviolent_ranks;
    
    -- Incident percentiles
    DROP TEMPORARY TABLE IF EXISTS tmp_incident_ranks;
    CREATE TEMPORARY TABLE tmp_incident_ranks AS
    SELECT 
        h3_index,
        ROUND((@row_num := @row_num + 1) * 100.0 / v_total_count, 0) as percentile
    FROM amisafe_h3_aggregated, (SELECT @row_num := 0) r
    WHERE h3_resolution = p_resolution
    ORDER BY incident_count ASC, h3_index ASC;
    
    UPDATE amisafe_h3_aggregated a
    INNER JOIN tmp_incident_ranks t ON a.h3_index = t.h3_index
    SET a.incident_percentile = t.percentile
    WHERE a.h3_resolution = p_resolution;
    
    DROP TEMPORARY TABLE tmp_incident_ranks;
    
    SELECT CONCAT('Statistical metrics calculated for resolution ', p_resolution, ' (', v_total_count, ' hexagons)') AS result;
END$$

-- ==============================================================================
-- 10. Calculate Risk Scores and Categories
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_risk_scores$$
CREATE PROCEDURE sp_calculate_risk_scores(
    IN p_resolution INT
)
BEGIN
    -- Calculate risk scores based on weighted z-scores
    -- Risk = (violent_z * 2.0) + (nonviolent_z * 1.0) + (incident_z * 0.5)
    UPDATE amisafe_h3_aggregated
    SET 
        risk_score = GREATEST(0, 
            (IFNULL(violent_crime_z_score, 0) * 2.0) + 
            (IFNULL(nonviolent_crime_z_score, 0) * 1.0) + 
            (IFNULL(incident_z_score, 0) * 0.5)
        ),
        
        -- Risk categories based on composite score
        risk_category = CASE
            WHEN ((IFNULL(violent_crime_z_score, 0) * 2.0) + 
                  (IFNULL(nonviolent_crime_z_score, 0) * 1.0) + 
                  (IFNULL(incident_z_score, 0) * 0.5)) >= 3.0 THEN 'CRITICAL'
            WHEN ((IFNULL(violent_crime_z_score, 0) * 2.0) + 
                  (IFNULL(nonviolent_crime_z_score, 0) * 1.0) + 
                  (IFNULL(incident_z_score, 0) * 0.5)) >= 1.5 THEN 'HIGH'
            WHEN ((IFNULL(violent_crime_z_score, 0) * 2.0) + 
                  (IFNULL(nonviolent_crime_z_score, 0) * 1.0) + 
                  (IFNULL(incident_z_score, 0) * 0.5)) >= 0.5 THEN 'MODERATE'
            ELSE 'LOW'
        END,
        
        -- Hotspot status based on percentiles
        hotspot_status = CASE
            WHEN incident_percentile >= 95 THEN 'EXTREME'
            WHEN incident_percentile >= 85 THEN 'HOT'
            WHEN incident_percentile >= 60 THEN 'WARM'
            ELSE 'COLD'
        END
        
    WHERE h3_resolution = p_resolution;
    
    SELECT CONCAT('Risk scores calculated for resolution ', p_resolution) AS result;
END$$

-- ==============================================================================
-- 11. Complete Analytics Pipeline for Resolution (Basic + Advanced)
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_complete_resolution_analytics$$
CREATE PROCEDURE sp_complete_resolution_analytics(
    IN p_resolution INT
)
BEGIN
    SELECT CONCAT('=== Starting Complete Analytics Pipeline for Resolution ', p_resolution, ' ===') AS status;
    
    -- Step 1: Basic analytics (crime types, temporal patterns, etc.)
    SELECT '📊 Step 1/3: Calculating basic analytics...' AS status;
    CALL sp_update_resolution_analytics(p_resolution);
    
    -- Step 2: Statistical metrics (z-scores, percentiles)
    SELECT '📈 Step 2/3: Calculating statistical metrics...' AS status;
    CALL sp_calculate_statistical_metrics(p_resolution);
    
    -- Step 3: Risk scores and categories
    SELECT '🎯 Step 3/3: Calculating risk scores...' AS status;
    CALL sp_calculate_risk_scores(p_resolution);
    
    SELECT CONCAT('✅ Complete analytics finished for resolution ', p_resolution) AS result;
END$$

-- ==============================================================================
-- 12. Complete Analytics Pipeline: All Time Windows (all-time + 12mo + 6mo)
-- Requires: stored_procedures_h3_analytics_windowed.sql to be loaded first
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_complete_all_windows$$
CREATE PROCEDURE sp_complete_all_windows(
    IN p_resolution INT
)
BEGIN
    SELECT CONCAT('=== Complete Analytics for Resolution ', p_resolution, ' (All Time Windows) ===') AS status;
    
    -- All-time analytics
    SELECT '📊 Pass 1/5: All-time basic analytics...' AS status;
    CALL sp_update_resolution_analytics(p_resolution);
    
    SELECT '📈 Pass 2/5: All-time statistical metrics...' AS status;
    CALL sp_calculate_statistical_metrics(p_resolution);
    
    SELECT '🎯 Pass 3/5: All-time risk scores...' AS status;
    CALL sp_calculate_risk_scores(p_resolution);
    
    -- Windowed analytics
    SELECT '🕐 Pass 4/5: 12-month and 6-month windowed analytics...' AS status;
    CALL sp_update_resolution_analytics_windowed(p_resolution);
    
    SELECT '📊 Pass 4a/5: 12-month statistical metrics...' AS status;
    CALL sp_calculate_statistical_metrics_windowed(p_resolution, 12);
    
    SELECT '🎯 Pass 4b/5: 12-month risk scores...' AS status;
    CALL sp_calculate_risk_scores_windowed(p_resolution, 12);
    
    SELECT '📊 Pass 5a/5: 6-month statistical metrics...' AS status;
    CALL sp_calculate_statistical_metrics_windowed(p_resolution, 6);
    
    SELECT '🎯 Pass 5b/5: 6-month risk scores...' AS status;
    CALL sp_calculate_risk_scores_windowed(p_resolution, 6);
    
    SELECT CONCAT('✅ ALL analytics complete for resolution ', p_resolution, ' (all-time + 12mo + 6mo)') AS result;
END$$

DELIMITER ;

-- ==============================================================================
-- Usage Examples:
-- ==============================================================================
-- Update single hexagon (basic analytics only):
--   CALL sp_update_hex_analytics('852a1343fffffff', 5);
--
-- Update all hexagons for resolution 13 (basic analytics only):
--   CALL sp_update_resolution_analytics(13);
--
-- Calculate statistical metrics for resolution (after basic analytics):
--   CALL sp_calculate_statistical_metrics(13);
--
-- Calculate risk scores for resolution (after statistical metrics):
--   CALL sp_calculate_risk_scores(13);
--
-- RECOMMENDED: Complete pipeline (basic + statistical + risk) for one resolution:
--   CALL sp_complete_resolution_analytics(13);
--
-- RECOMMENDED: Complete pipeline with ALL time windows (all-time + 12mo + 6mo):
--   NOTE: Requires stored_procedures_h3_analytics_windowed.sql to be loaded
--   CALL sp_complete_all_windows(13);
--
-- Process all resolutions with complete analytics (run individually, highest to lowest):
--   CALL sp_complete_all_windows(13);  -- ~177K hexagons, all windows
--   CALL sp_complete_all_windows(12);  -- ~146K hexagons, all windows
--   CALL sp_complete_all_windows(11);  -- ~70K hexagons, all windows
--   CALL sp_complete_all_windows(10);  -- ~17K hexagons, all windows
--   CALL sp_complete_all_windows(9);   -- ~3K hexagons, all windows
--   CALL sp_complete_all_windows(8);   -- ~545 hexagons, all windows
--   CALL sp_complete_all_windows(7);   -- ~93 hexagons, all windows
--   CALL sp_complete_all_windows(6);   -- ~23 hexagons, all windows
--   CALL sp_complete_all_windows(5);   -- ~5 hexagons, all windows

