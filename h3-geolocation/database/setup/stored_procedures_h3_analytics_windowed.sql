-- ==============================================================================
-- AmISafe H3 Analytics Stored Procedures - WINDOWED VERSIONS
-- Version: 2.0.0 (2025-11-26)
-- Calculates 12-month and 6-month windowed analytics
-- Fixed: SQL syntax error with PREPARE/EXECUTE and INTO clause
-- ==============================================================================

DELIMITER $$

-- ==============================================================================
-- WINDOWED: Calculate Top Crime Type
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_top_crime_type_windowed$$
CREATE PROCEDURE sp_calculate_top_crime_type_windowed(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    IN p_months INT,  -- NULL for all-time, 12 for 12mo, 6 for 6mo
    OUT p_top_crime_type VARCHAR(10)
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    DECLARE date_filter VARCHAR(200);
    
    SET h3_col = CONCAT('h3_res_', p_resolution);
    SET date_filter = CASE 
        WHEN p_months IS NULL THEN ''
        ELSE CONCAT('AND incident_datetime >= DATE_SUB(NOW(), INTERVAL ', p_months, ' MONTH)')
    END;
    
    SET @query = CONCAT('
        SELECT ucr_general INTO @top_crime
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ? 
            AND is_duplicate = FALSE 
            AND incident_datetime IS NOT NULL
            AND ucr_general IS NOT NULL
            ', date_filter, '
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
-- WINDOWED: Calculate Crime Diversity Index
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_crime_diversity_windowed$$
CREATE PROCEDURE sp_calculate_crime_diversity_windowed(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    IN p_months INT,
    OUT p_diversity_index DECIMAL(10,3)
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    DECLARE date_filter VARCHAR(200);
    DECLARE total_crimes INT;
    DECLARE diversity DECIMAL(10,3) DEFAULT 0.0;
    
    SET h3_col = CONCAT('h3_res_', p_resolution);
    SET date_filter = CASE 
        WHEN p_months IS NULL THEN ''
        ELSE CONCAT('AND incident_datetime >= DATE_SUB(NOW(), INTERVAL ', p_months, ' MONTH)')
    END;
    
    -- Get total crime count
    SET @count_query = CONCAT('
        SELECT COUNT(*) INTO @total
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ? 
            AND is_duplicate = FALSE 
            AND incident_datetime IS NOT NULL
            ', date_filter, '
    ');
    
    PREPARE stmt FROM @count_query;
    SET @h3_idx = p_h3_index;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    
    SET total_crimes = @total;
    
    IF total_crimes > 1 THEN
        SET @diversity_query = CONCAT('
            SELECT -SUM((cnt / ?) * LN(cnt / ?)) INTO @div
            FROM (
                SELECT COUNT(*) as cnt
                FROM amisafe_clean_incidents
                WHERE ', h3_col, ' = ? 
                    AND is_duplicate = FALSE 
                    AND incident_datetime IS NOT NULL
                    AND ucr_general IS NOT NULL
                    ', date_filter, '
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
-- WINDOWED: Calculate Temporal Patterns
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_temporal_patterns_windowed$$
CREATE PROCEDURE sp_calculate_temporal_patterns_windowed(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    IN p_months INT,
    OUT p_by_hour JSON,
    OUT p_by_dow JSON,
    OUT p_by_month JSON,
    OUT p_peak_hour INT,
    OUT p_peak_dow INT
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    DECLARE date_filter VARCHAR(200);
    
    SET h3_col = CONCAT('h3_res_', p_resolution);
    SET date_filter = CASE 
        WHEN p_months IS NULL THEN ''
        ELSE CONCAT('AND incident_datetime >= DATE_SUB(NOW(), INTERVAL ', p_months, ' MONTH)')
    END;
    
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
                    ', date_filter, '
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
                    ', date_filter, '
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
                    ', date_filter, '
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
            ', date_filter, '
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
            ', date_filter, '
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
-- WINDOWED: Calculate Violent vs Non-Violent Stats
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_violent_stats_windowed$$
CREATE PROCEDURE sp_calculate_violent_stats_windowed(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    IN p_months INT,
    OUT p_violent_count INT,
    OUT p_nonviolent_count INT,
    OUT p_violent_pct DECIMAL(5,2)
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    DECLARE date_filter VARCHAR(200);
    DECLARE total_count INT;
    
    SET h3_col = CONCAT('h3_res_', p_resolution);
    SET date_filter = CASE 
        WHEN p_months IS NULL THEN ''
        ELSE CONCAT('AND incident_datetime >= DATE_SUB(NOW(), INTERVAL ', p_months, ' MONTH)')
    END;
    
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
            ', date_filter, '
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
-- WINDOWED: Calculate Unique Incident Types
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_unique_types_windowed$$
CREATE PROCEDURE sp_calculate_unique_types_windowed(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT,
    IN p_months INT,
    OUT p_unique_types INT
)
BEGIN
    DECLARE h3_col VARCHAR(20);
    DECLARE date_filter VARCHAR(200);
    
    SET h3_col = CONCAT('h3_res_', p_resolution);
    SET date_filter = CASE 
        WHEN p_months IS NULL THEN ''
        ELSE CONCAT('AND incident_datetime >= DATE_SUB(NOW(), INTERVAL ', p_months, ' MONTH)')
    END;
    
    SET @unique_query = CONCAT('
        SELECT COUNT(DISTINCT ucr_general) INTO @unique
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ? 
            AND is_duplicate = FALSE 
            AND incident_datetime IS NOT NULL
            AND ucr_general IS NOT NULL
            ', date_filter, '
    ');
    
    PREPARE stmt FROM @unique_query;
    SET @h3_idx = p_h3_index;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    
    SET p_unique_types = IFNULL(@unique, 0);
END$$

-- ==============================================================================
-- WINDOWED: Master Update for Single Hexagon (12mo and 6mo windows)
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_update_hex_analytics_windowed$$
CREATE PROCEDURE sp_update_hex_analytics_windowed(
    IN p_h3_index VARCHAR(20),
    IN p_resolution INT
)
BEGIN
    -- Variables for 12-month window
    DECLARE v_incident_count_12mo INT;
    DECLARE v_top_crime_12mo VARCHAR(10);
    DECLARE v_diversity_12mo DECIMAL(10,3);
    DECLARE v_by_hour_12mo JSON;
    DECLARE v_by_dow_12mo JSON;
    DECLARE v_by_month_12mo JSON;
    DECLARE v_peak_hour_12mo INT;
    DECLARE v_peak_dow_12mo INT;
    DECLARE v_violent_count_12mo INT;
    DECLARE v_nonviolent_count_12mo INT;
    DECLARE v_violent_pct_12mo DECIMAL(5,2);
    DECLARE v_unique_types_12mo INT;
    
    -- Variables for 6-month window
    DECLARE v_incident_count_6mo INT;
    DECLARE v_top_crime_6mo VARCHAR(10);
    DECLARE v_diversity_6mo DECIMAL(10,3);
    DECLARE v_by_hour_6mo JSON;
    DECLARE v_by_dow_6mo JSON;
    DECLARE v_by_month_6mo JSON;
    DECLARE v_peak_hour_6mo INT;
    DECLARE v_peak_dow_6mo INT;
    DECLARE v_violent_count_6mo INT;
    DECLARE v_nonviolent_count_6mo INT;
    DECLARE v_violent_pct_6mo DECIMAL(5,2);
    DECLARE v_unique_types_6mo INT;
    
    DECLARE h3_col VARCHAR(20);
    SET h3_col = CONCAT('h3_res_', p_resolution);
    
    -- CRITICAL: Calculate 12-month total incident count
    SET @count_12mo_query = CONCAT('
        SELECT COUNT(*) INTO @result_12mo
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ?
            AND is_duplicate = FALSE
            AND incident_datetime >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    ');
    PREPARE stmt FROM @count_12mo_query;
    SET @h3_idx = p_h3_index;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    SET v_incident_count_12mo = IFNULL(@result_12mo, 0);
    
    -- CRITICAL: Calculate 6-month total incident count  
    SET @count_6mo_query = CONCAT('
        SELECT COUNT(*) INTO @result_6mo
        FROM amisafe_clean_incidents
        WHERE ', h3_col, ' = ?
            AND is_duplicate = FALSE
            AND incident_datetime >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ');
    PREPARE stmt FROM @count_6mo_query;
    EXECUTE stmt USING @h3_idx;
    DEALLOCATE PREPARE stmt;
    SET v_incident_count_6mo = IFNULL(@result_6mo, 0);
    
    -- Calculate 12-month window analytics
    CALL sp_calculate_top_crime_type_windowed(p_h3_index, p_resolution, 12, v_top_crime_12mo);
    CALL sp_calculate_crime_diversity_windowed(p_h3_index, p_resolution, 12, v_diversity_12mo);
    CALL sp_calculate_temporal_patterns_windowed(p_h3_index, p_resolution, 12, v_by_hour_12mo, v_by_dow_12mo, v_by_month_12mo, v_peak_hour_12mo, v_peak_dow_12mo);
    CALL sp_calculate_violent_stats_windowed(p_h3_index, p_resolution, 12, v_violent_count_12mo, v_nonviolent_count_12mo, v_violent_pct_12mo);
    CALL sp_calculate_unique_types_windowed(p_h3_index, p_resolution, 12, v_unique_types_12mo);
    
    -- Calculate 6-month window analytics
    CALL sp_calculate_top_crime_type_windowed(p_h3_index, p_resolution, 6, v_top_crime_6mo);
    CALL sp_calculate_crime_diversity_windowed(p_h3_index, p_resolution, 6, v_diversity_6mo);
    CALL sp_calculate_temporal_patterns_windowed(p_h3_index, p_resolution, 6, v_by_hour_6mo, v_by_dow_6mo, v_by_month_6mo, v_peak_hour_6mo, v_peak_dow_6mo);
    CALL sp_calculate_violent_stats_windowed(p_h3_index, p_resolution, 6, v_violent_count_6mo, v_nonviolent_count_6mo, v_violent_pct_6mo);
    CALL sp_calculate_unique_types_windowed(p_h3_index, p_resolution, 6, v_unique_types_6mo);
    
    -- Update the aggregated record with windowed analytics
    UPDATE amisafe_h3_aggregated
    SET 
        -- 12-month window
        incident_count_12mo = v_incident_count_12mo,
        top_crime_type_12mo = v_top_crime_12mo,
        crime_diversity_index_12mo = v_diversity_12mo,
        incidents_by_hour_12mo = v_by_hour_12mo,
        incidents_by_dow_12mo = v_by_dow_12mo,
        incidents_by_month_12mo = v_by_month_12mo,
        peak_hour_12mo = v_peak_hour_12mo,
        peak_dow_12mo = v_peak_dow_12mo,
        violent_crime_count_12mo = v_violent_count_12mo,
        nonviolent_crime_count_12mo = v_nonviolent_count_12mo,
        violent_crime_percentage_12mo = v_violent_pct_12mo,
        unique_incident_types_12mo = v_unique_types_12mo,
        
        -- 6-month window
        incident_count_6mo = v_incident_count_6mo,
        top_crime_type_6mo = v_top_crime_6mo,
        crime_diversity_index_6mo = v_diversity_6mo,
        incidents_by_hour_6mo = v_by_hour_6mo,
        incidents_by_dow_6mo = v_by_dow_6mo,
        incidents_by_month_6mo = v_by_month_6mo,
        peak_hour_6mo = v_peak_hour_6mo,
        peak_dow_6mo = v_peak_dow_6mo,
        violent_crime_count_6mo = v_violent_count_6mo,
        nonviolent_crime_count_6mo = v_nonviolent_count_6mo,
        violent_crime_percentage_6mo = v_violent_pct_6mo,
        unique_incident_types_6mo = v_unique_types_6mo
        
    WHERE h3_index = p_h3_index 
        AND h3_resolution = p_resolution;
END$$

-- ==============================================================================
-- WINDOWED: Batch Update for Resolution
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_update_resolution_analytics_windowed$$
CREATE PROCEDURE sp_update_resolution_analytics_windowed(
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
    
    SELECT COUNT(*) INTO v_total
    FROM amisafe_h3_aggregated 
    WHERE h3_resolution = p_resolution;
    
    SELECT CONCAT('Starting windowed analytics for resolution ', p_resolution, ': ', v_total, ' hexagons') AS status;
    
    OPEN hex_cursor;
    
    read_loop: LOOP
        FETCH hex_cursor INTO v_h3_index;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET v_counter = v_counter + 1;
        
        CALL sp_update_hex_analytics_windowed(v_h3_index, p_resolution);
        
        IF v_counter % 100 = 0 THEN
            SELECT CONCAT('Windowed: ', v_counter, '/', v_total, ' hexagons (', 
                   ROUND((v_counter/v_total)*100, 1), '%)') AS progress;
        END IF;
    END LOOP;
    
    CLOSE hex_cursor;
    
    SELECT CONCAT('Completed windowed analytics for resolution ', p_resolution, ': ', v_counter, ' hexagons') AS result;
END$$

-- ==============================================================================
-- WINDOWED: Statistical Metrics (Z-Scores, Percentiles) for 12mo and 6mo
-- OPTIMIZED: Uses temp table for O(n log n) percentile calculation instead of O(n²)
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_statistical_metrics_windowed$$
CREATE PROCEDURE sp_calculate_statistical_metrics_windowed(
    IN p_resolution INT,
    IN p_months INT  -- 12 or 6
)
BEGIN
    DECLARE v_violent_mean DECIMAL(10,2);
    DECLARE v_violent_stddev DECIMAL(10,2);
    DECLARE v_nonviolent_mean DECIMAL(10,2);
    DECLARE v_nonviolent_stddev DECIMAL(10,2);
    DECLARE v_incident_mean DECIMAL(10,2);
    DECLARE v_incident_stddev DECIMAL(10,2);
    DECLARE v_suffix VARCHAR(10);
    DECLARE v_total_count INT;
    
    SET v_suffix = CONCAT('_', p_months, 'mo');
    
    -- Get population statistics from windowed counts
    IF p_months = 12 THEN
        SELECT 
            AVG(violent_crime_count_12mo),
            STDDEV(violent_crime_count_12mo),
            AVG(nonviolent_crime_count_12mo),
            STDDEV(nonviolent_crime_count_12mo),
            AVG(incident_count_12mo),
            STDDEV(incident_count_12mo),
            COUNT(*)
        INTO 
            v_violent_mean, v_violent_stddev,
            v_nonviolent_mean, v_nonviolent_stddev,
            v_incident_mean, v_incident_stddev,
            v_total_count
        FROM amisafe_h3_aggregated
        WHERE h3_resolution = p_resolution;
    ELSE
        SELECT 
            AVG(violent_crime_count_6mo),
            STDDEV(violent_crime_count_6mo),
            AVG(nonviolent_crime_count_6mo),
            STDDEV(nonviolent_crime_count_6mo),
            AVG(incident_count_6mo),
            STDDEV(incident_count_6mo),
            COUNT(*)
        INTO 
            v_violent_mean, v_violent_stddev,
            v_nonviolent_mean, v_nonviolent_stddev,
            v_incident_mean, v_incident_stddev,
            v_total_count
        FROM amisafe_h3_aggregated
        WHERE h3_resolution = p_resolution;
    END IF;
    
    -- Step 1: Update z-scores and stats (fast)
    IF p_months = 12 THEN
        UPDATE amisafe_h3_aggregated
        SET 
            violent_crime_z_score_12mo = CASE 
                WHEN v_violent_stddev > 0 THEN (violent_crime_count_12mo - v_violent_mean) / v_violent_stddev
                ELSE 0 
            END,
            nonviolent_crime_z_score_12mo = CASE 
                WHEN v_nonviolent_stddev > 0 THEN (nonviolent_crime_count_12mo - v_nonviolent_mean) / v_nonviolent_stddev
                ELSE 0 
            END,
            incident_z_score_12mo = CASE 
                WHEN v_incident_stddev > 0 THEN (incident_count_12mo - v_incident_mean) / v_incident_stddev
                ELSE 0 
            END,
            violent_crime_mean_12mo = v_violent_mean,
            violent_crime_std_dev_12mo = v_violent_stddev,
            nonviolent_crime_mean_12mo = v_nonviolent_mean,
            nonviolent_crime_std_dev_12mo = v_nonviolent_stddev,
            incident_mean_12mo = v_incident_mean,
            incident_std_dev_12mo = v_incident_stddev
        WHERE h3_resolution = p_resolution;
    ELSE
        UPDATE amisafe_h3_aggregated
        SET 
            violent_crime_z_score_6mo = CASE 
                WHEN v_violent_stddev > 0 THEN (violent_crime_count_6mo - v_violent_mean) / v_violent_stddev
                ELSE 0 
            END,
            nonviolent_crime_z_score_6mo = CASE 
                WHEN v_nonviolent_stddev > 0 THEN (nonviolent_crime_count_6mo - v_nonviolent_mean) / v_nonviolent_stddev
                ELSE 0 
            END,
            incident_z_score_6mo = CASE 
                WHEN v_incident_stddev > 0 THEN (incident_count_6mo - v_incident_mean) / v_incident_stddev
                ELSE 0 
            END,
            violent_crime_mean_6mo = v_violent_mean,
            violent_crime_std_dev_6mo = v_violent_stddev,
            nonviolent_crime_mean_6mo = v_nonviolent_mean,
            nonviolent_crime_std_dev_6mo = v_nonviolent_stddev,
            incident_mean_6mo = v_incident_mean,
            incident_std_dev_6mo = v_incident_stddev
        WHERE h3_resolution = p_resolution;
    END IF;
    
    -- Step 2: Calculate percentiles using temp tables with ranking
    -- Violent crime percentiles
    DROP TEMPORARY TABLE IF EXISTS tmp_violent_ranks_windowed;
    
    IF p_months = 12 THEN
        CREATE TEMPORARY TABLE tmp_violent_ranks_windowed AS
        SELECT 
            h3_index,
            ROUND((@row_num := @row_num + 1) * 100.0 / v_total_count, 0) as percentile
        FROM amisafe_h3_aggregated, (SELECT @row_num := 0) r
        WHERE h3_resolution = p_resolution
        ORDER BY violent_crime_count_12mo ASC, h3_index ASC;
        
        UPDATE amisafe_h3_aggregated a
        INNER JOIN tmp_violent_ranks_windowed t ON a.h3_index = t.h3_index
        SET a.violent_crime_percentile_12mo = t.percentile
        WHERE a.h3_resolution = p_resolution;
    ELSE
        CREATE TEMPORARY TABLE tmp_violent_ranks_windowed AS
        SELECT 
            h3_index,
            ROUND((@row_num := @row_num + 1) * 100.0 / v_total_count, 0) as percentile
        FROM amisafe_h3_aggregated, (SELECT @row_num := 0) r
        WHERE h3_resolution = p_resolution
        ORDER BY violent_crime_count_6mo ASC, h3_index ASC;
        
        UPDATE amisafe_h3_aggregated a
        INNER JOIN tmp_violent_ranks_windowed t ON a.h3_index = t.h3_index
        SET a.violent_crime_percentile_6mo = t.percentile
        WHERE a.h3_resolution = p_resolution;
    END IF;
    
    DROP TEMPORARY TABLE tmp_violent_ranks_windowed;
    
    -- Nonviolent crime percentiles
    DROP TEMPORARY TABLE IF EXISTS tmp_nonviolent_ranks_windowed;
    
    IF p_months = 12 THEN
        CREATE TEMPORARY TABLE tmp_nonviolent_ranks_windowed AS
        SELECT 
            h3_index,
            ROUND((@row_num := @row_num + 1) * 100.0 / v_total_count, 0) as percentile
        FROM amisafe_h3_aggregated, (SELECT @row_num := 0) r
        WHERE h3_resolution = p_resolution
        ORDER BY nonviolent_crime_count_12mo ASC, h3_index ASC;
        
        UPDATE amisafe_h3_aggregated a
        INNER JOIN tmp_nonviolent_ranks_windowed t ON a.h3_index = t.h3_index
        SET a.nonviolent_crime_percentile_12mo = t.percentile
        WHERE a.h3_resolution = p_resolution;
    ELSE
        CREATE TEMPORARY TABLE tmp_nonviolent_ranks_windowed AS
        SELECT 
            h3_index,
            ROUND((@row_num := @row_num + 1) * 100.0 / v_total_count, 0) as percentile
        FROM amisafe_h3_aggregated, (SELECT @row_num := 0) r
        WHERE h3_resolution = p_resolution
        ORDER BY nonviolent_crime_count_6mo ASC, h3_index ASC;
        
        UPDATE amisafe_h3_aggregated a
        INNER JOIN tmp_nonviolent_ranks_windowed t ON a.h3_index = t.h3_index
        SET a.nonviolent_crime_percentile_6mo = t.percentile
        WHERE a.h3_resolution = p_resolution;
    END IF;
    
    DROP TEMPORARY TABLE tmp_nonviolent_ranks_windowed;
    
    -- Incident percentiles
    DROP TEMPORARY TABLE IF EXISTS tmp_incident_ranks_windowed;
    
    IF p_months = 12 THEN
        CREATE TEMPORARY TABLE tmp_incident_ranks_windowed AS
        SELECT 
            h3_index,
            ROUND((@row_num := @row_num + 1) * 100.0 / v_total_count, 0) as percentile
        FROM amisafe_h3_aggregated, (SELECT @row_num := 0) r
        WHERE h3_resolution = p_resolution
        ORDER BY incident_count_12mo ASC, h3_index ASC;
        
        UPDATE amisafe_h3_aggregated a
        INNER JOIN tmp_incident_ranks_windowed t ON a.h3_index = t.h3_index
        SET a.incident_percentile_12mo = t.percentile
        WHERE a.h3_resolution = p_resolution;
    ELSE
        CREATE TEMPORARY TABLE tmp_incident_ranks_windowed AS
        SELECT 
            h3_index,
            ROUND((@row_num := @row_num + 1) * 100.0 / v_total_count, 0) as percentile
        FROM amisafe_h3_aggregated, (SELECT @row_num := 0) r
        WHERE h3_resolution = p_resolution
        ORDER BY incident_count_6mo ASC, h3_index ASC;
        
        UPDATE amisafe_h3_aggregated a
        INNER JOIN tmp_incident_ranks_windowed t ON a.h3_index = t.h3_index
        SET a.incident_percentile_6mo = t.percentile
        WHERE a.h3_resolution = p_resolution;
    END IF;
    
    DROP TEMPORARY TABLE tmp_incident_ranks_windowed;
    
    SELECT CONCAT('Statistical metrics calculated for resolution ', p_resolution, ' (', p_months, '-month window, ', v_total_count, ' hexagons)') AS result;
END$$

-- ==============================================================================
-- WINDOWED: Risk Scores for 12mo and 6mo windows
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_risk_scores_windowed$$
CREATE PROCEDURE sp_calculate_risk_scores_windowed(
    IN p_resolution INT,
    IN p_months INT  -- 12 or 6
)
BEGIN
    IF p_months = 12 THEN
        UPDATE amisafe_h3_aggregated
        SET 
            risk_score_12mo = GREATEST(0, 
                (IFNULL(violent_crime_z_score_12mo, 0) * 2.0) + 
                (IFNULL(nonviolent_crime_z_score_12mo, 0) * 1.0) + 
                (IFNULL(incident_z_score_12mo, 0) * 0.5)
            ),
            risk_category_12mo = CASE
                WHEN ((IFNULL(violent_crime_z_score_12mo, 0) * 2.0) + 
                      (IFNULL(nonviolent_crime_z_score_12mo, 0) * 1.0) + 
                      (IFNULL(incident_z_score_12mo, 0) * 0.5)) >= 3.0 THEN 'CRITICAL'
                WHEN ((IFNULL(violent_crime_z_score_12mo, 0) * 2.0) + 
                      (IFNULL(nonviolent_crime_z_score_12mo, 0) * 1.0) + 
                      (IFNULL(incident_z_score_12mo, 0) * 0.5)) >= 1.5 THEN 'HIGH'
                WHEN ((IFNULL(violent_crime_z_score_12mo, 0) * 2.0) + 
                      (IFNULL(nonviolent_crime_z_score_12mo, 0) * 1.0) + 
                      (IFNULL(incident_z_score_12mo, 0) * 0.5)) >= 0.5 THEN 'MODERATE'
                ELSE 'LOW'
            END,
            hotspot_status_12mo = CASE
                WHEN incident_percentile_12mo >= 95 THEN 'EXTREME'
                WHEN incident_percentile_12mo >= 85 THEN 'HOT'
                WHEN incident_percentile_12mo >= 60 THEN 'WARM'
                ELSE 'COLD'
            END
        WHERE h3_resolution = p_resolution;
    ELSE
        UPDATE amisafe_h3_aggregated
        SET 
            risk_score_6mo = GREATEST(0, 
                (IFNULL(violent_crime_z_score_6mo, 0) * 2.0) + 
                (IFNULL(nonviolent_crime_z_score_6mo, 0) * 1.0) + 
                (IFNULL(incident_z_score_6mo, 0) * 0.5)
            ),
            risk_category_6mo = CASE
                WHEN ((IFNULL(violent_crime_z_score_6mo, 0) * 2.0) + 
                      (IFNULL(nonviolent_crime_z_score_6mo, 0) * 1.0) + 
                      (IFNULL(incident_z_score_6mo, 0) * 0.5)) >= 3.0 THEN 'CRITICAL'
                WHEN ((IFNULL(violent_crime_z_score_6mo, 0) * 2.0) + 
                      (IFNULL(nonviolent_crime_z_score_6mo, 0) * 1.0) + 
                      (IFNULL(incident_z_score_6mo, 0) * 0.5)) >= 1.5 THEN 'HIGH'
                WHEN ((IFNULL(violent_crime_z_score_6mo, 0) * 2.0) + 
                      (IFNULL(nonviolent_crime_z_score_6mo, 0) * 1.0) + 
                      (IFNULL(incident_z_score_6mo, 0) * 0.5)) >= 0.5 THEN 'MODERATE'
                ELSE 'LOW'
            END,
            hotspot_status_6mo = CASE
                WHEN incident_percentile_6mo >= 95 THEN 'EXTREME'
                WHEN incident_percentile_6mo >= 85 THEN 'HOT'
                WHEN incident_percentile_6mo >= 60 THEN 'WARM'
                ELSE 'COLD'
            END
        WHERE h3_resolution = p_resolution;
    END IF;
    
    SELECT CONCAT('Risk scores calculated for resolution ', p_resolution, ' (', p_months, '-month window)') AS result;
END$$

DELIMITER ;

-- ==============================================================================
-- Usage Examples - WINDOWED ANALYTICS:
-- ==============================================================================
-- Update single hexagon with windowed analytics (12mo + 6mo):
--   CALL sp_update_hex_analytics_windowed('8d2a13400000c3f', 13);
--
-- Update all hexagons in resolution with windowed analytics:
--   CALL sp_update_resolution_analytics_windowed(13);
--
-- Calculate 12-month statistical metrics:
--   CALL sp_calculate_statistical_metrics_windowed(13, 12);
--
-- Calculate 6-month risk scores:
--   CALL sp_calculate_risk_scores_windowed(13, 6);
--
-- RECOMMENDED: For complete pipeline with all time windows, use sp_complete_all_windows()
--              which is defined in stored_procedures_h3_analytics.sql and orchestrates
--              both all-time and windowed analytics together.
