-- ============================================================
-- Triggers that keep rooms.status in step with the beds
-- Run AFTER aiven_01_schema.sql and BEFORE aiven_03_seed.sql.
-- Safe to run again: it drops the old triggers first.
--
-- On some managed MySQL services (binary logging on, no SUPER
-- privilege) this fails with error 1419. If it does, skip this file;
-- the API can update the room status itself instead.
-- ============================================================

DROP TRIGGER IF EXISTS beds_after_insert;
DROP TRIGGER IF EXISTS beds_after_update;
DROP TRIGGER IF EXISTS beds_after_delete;
DROP PROCEDURE IF EXISTS refresh_room_status;

-- ---------- Keep rooms.status correct ----------
-- Takes two room IDs (the same one twice, or the old and new room when a bed
-- moves). Only Available/Allocated rooms are touched, so Utility and
-- Restricted rooms keep the status you set.
-- Each body is a single statement, so no DELIMITER is needed.
CREATE PROCEDURE refresh_room_status(
  IN p_room_a VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN p_room_b VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci)
  UPDATE rooms
  SET status = CASE
        WHEN NOT EXISTS (SELECT 1 FROM beds WHERE beds.room_id = rooms.room_id) THEN 'Available'
        WHEN EXISTS (SELECT 1 FROM beds WHERE beds.room_id = rooms.room_id AND beds.status = 'Available') THEN 'Available'
        ELSE 'Allocated'
      END
  WHERE rooms.room_id IN (p_room_a, p_room_b)
    AND rooms.status IN ('Available', 'Allocated');

CREATE TRIGGER beds_after_insert AFTER INSERT ON beds
  FOR EACH ROW CALL refresh_room_status(NEW.room_id, NEW.room_id);

CREATE TRIGGER beds_after_update AFTER UPDATE ON beds
  FOR EACH ROW CALL refresh_room_status(NEW.room_id, OLD.room_id);

CREATE TRIGGER beds_after_delete AFTER DELETE ON beds
  FOR EACH ROW CALL refresh_room_status(OLD.room_id, OLD.room_id);
