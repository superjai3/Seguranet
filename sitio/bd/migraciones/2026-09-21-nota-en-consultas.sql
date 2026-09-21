-- Nota interna en las consultas, para el panel.
--
-- esquema.sql ya trae la columna: esto es sólo para una base que se haya
-- importado antes del 21/09/2026. En una base nueva no hace falta correrlo.
--
-- MySQL no tiene "ADD COLUMN IF NOT EXISTS" en todas las versiones, así que si
-- la columna ya está, esto da error 1060 y no pasa nada: es la señal de que la
-- base ya estaba al día.

ALTER TABLE consultas
    ADD COLUMN nota VARCHAR(1000) NOT NULL DEFAULT '' AFTER estado;
