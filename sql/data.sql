DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='Projects';
INSERT INTO llx_overwrite_trans (entity, lang, transkey, transvalue) VALUES
(1, 'fr_FR', 'Projects', 'Chantier/Affaire');