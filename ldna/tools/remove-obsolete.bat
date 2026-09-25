@echo off
REM Deletes files from older versions that are no longer part of the project.
REM Run it from the ldna folder after extracting a new zip over your repository.
REM Safe to run more than once. Then: git add -A  and commit.
cd /d "%~dp0.."
for %%F in (
  backend\api\assignments.php
  backend\api\gaps.php
  backend\api\profile-trainings.php
  backend\api\self-assessment.php
  backend\seed\core_ratings.json
  frontend\src\components\AssignProfile.vue
  frontend\src\components\CompareTable.vue
  frontend\src\components\GapTable.vue
  frontend\src\views\CompetencyMapView.vue
  frontend\src\views\GapReviewView.vue
  frontend\src\views\SelfAssessmentView.vue
  package-lock.json
) do if exist "%%F" (del "%%F" & echo removed %%F)
REM Old built files: the current ones are listed in frontend\dist\index.html; stale hashed assets are harmless
REM but clutter git, so rebuild-free cleanup keeps only files referenced by index.html and lazy chunks.
echo Done.
