# Updating your repository from a zip

Zips are named `ldna-<date>-<time>.zip` and include the `.gitignore` files. They never contain
`.git`, `backend/data`, `frontend/node_modules`, `mock-portal/photos` or your mock-portal records
(`mock-portal/data/employees.json`, `mock-portal/data/plantilla_items.json`), so extracting one over
your repository keeps your history, local data, photos and test employees.

1. Delete the `frontend\dist` folder (the zip has a complete new one; this avoids old built files piling up).
2. Extract the zip **over** your `ldna` repository folder and choose "Replace" for every file.
3. Run `tools\remove-obsolete.bat` to delete files that older versions left behind.
4. `git add -A`, `git commit -m "..."`, `git push`.

Check the bottom-left of the sidebar: the build time must match the zip name.
