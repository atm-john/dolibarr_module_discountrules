-- Copyright (C) ---Put here your own copyright and developer email---
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.



ALTER TABLE llx_discountrule_category_project
	DROP INDEX fk_discountrule,
	DROP INDEX fk_discountrule_fk_category_project
;

ALTER TABLE llx_discountrule_category_project
  ADD KEY fk_discountrule (fk_discountrule),
  ADD UNIQUE fk_discountrule_fk_category_project ( fk_discountrule, fk_category_project);
;

ALTER TABLE llx_discountrule_category_project ADD CONSTRAINT llx_discountrule_category_project_fk_discountrule FOREIGN KEY (fk_discountrule) REFERENCES llx_discountrule(rowid);

