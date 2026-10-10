-- Применяется командой настройки из терминала; повторный запуск не заменяет данные каталога.
CREATE TABLE IF NOT EXISTS admin_accounts (
    id smallint PRIMARY KEY CHECK (id = 1),
    login varchar(64) NOT NULL UNIQUE CHECK (login ~ '^[a-zA-Z0-9_.-]{3,64}$'),
    password_hash varchar(255) NOT NULL,
    role varchar(10) NOT NULL DEFAULT 'admin' CHECK (role = 'admin'),
    active boolean NOT NULL DEFAULT true
);

-- Сохраняем время изменения каталога даже после удаления последнего или недавно изменённого шрифта.
CREATE TABLE IF NOT EXISTS catalog_state (
    id smallint PRIMARY KEY CHECK (id = 1),
    changed_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO catalog_state (id) VALUES (1) ON CONFLICT (id) DO NOTHING;

-- Одна форма может поменять местами характеристики двух начертаний или переместить два файла в рамках одной неделимой операции.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'font_faces'::regclass
        AND conname = 'font_faces_font_id_weight_style_key' AND NOT condeferrable) THEN
        ALTER TABLE font_faces DROP CONSTRAINT font_faces_font_id_weight_style_key;
        ALTER TABLE font_faces ADD CONSTRAINT font_faces_font_id_weight_style_key
            UNIQUE (font_id, weight, style) DEFERRABLE INITIALLY DEFERRED;
    END IF;
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'font_files'::regclass
        AND conname = 'font_files_face_id_format_key' AND NOT condeferrable) THEN
        ALTER TABLE font_files DROP CONSTRAINT font_files_face_id_format_key;
        ALTER TABLE font_files ADD CONSTRAINT font_files_face_id_format_key
            UNIQUE (face_id, format) DEFERRABLE INITIALLY DEFERRED;
    END IF;
END;
$$;
