# Code Duplication Resolution Strategy

## Issue
The codebase has significant duplication between:
- `/src/` directory (newer structure)
- `/nuclear-engagement/inc/` directory (legacy structure)

## Resolution Plan

### Phase 1: Establish Canonical Location
- Keep `/nuclear-engagement/inc/` as the canonical location (active plugin directory)
- Mark `/src/` as deprecated and create compatibility layer

### Phase 2: Consolidation
1. Update all references to use canonical location
2. Create compatibility wrappers in `/src/` that delegate to `/nuclear-engagement/inc/`
3. Add deprecation notices

### Phase 3: Cleanup (Future)
- Remove `/src/` directory entirely in next major version
- Update build processes and documentation

## Files to Consolidate

### Theme Services (Duplicated)
- ThemeConfigConverter.php
- ThemeCssGenerator.php  
- ThemeEventManager.php
- ThemeLoader.php
- ThemeMigrationService.php
- ThemeSettingsService.php
- ThemeValidator.php (only in nuclear-engagement)

### Database Schema (Duplicated)
- ThemeSchema.php

### Repositories (Duplicated) 
- ThemeRepository.php

### Models (Duplicated)
- Theme.php

## Implementation
Create compatibility wrappers to maintain backward compatibility during transition.