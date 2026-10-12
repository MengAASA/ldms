DELIMITER $$
DROP TRIGGER IF EXISTS trg_departments_hod_update$$
CREATE TRIGGER trg_departments_hod_update
AFTER UPDATE ON departments
FOR EACH ROW
BEGIN
    IF NOT (NEW.hod_user_id <=> OLD.hod_user_id) THEN
        -- Every staff in the department follows the new HOD. The HOD's own
        -- row is set to 0 rather than skipped: skipping it (the 2026-09-04
        -- version) avoided the self-loop but left the new HOD still
        -- reporting to whoever they reported to before - usually the HOD
        -- they just replaced - which showed up as a second HOD for the
        -- department in the TNA status table (M0440, 2026-10-12).
        UPDATE user SET hodid = IF(id = NEW.hod_user_id, 0, COALESCE(NEW.hod_user_id, 0))
        WHERE department_id = NEW.id;
    END IF;
END$$
DELIMITER ;
