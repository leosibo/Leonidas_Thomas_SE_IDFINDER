1. Extract the ZIP. Copy the entire `idfinder` folder into the AMPPS `www` folder.
2. Start Apache and MySQL in AMPPS. Open phpMyAdmin from AMPPS and import `idfinder/database/idfinder.sql`. The script creates and selects the `idfinder_m4` database.
3. Open `idfinder/config.php` if needed and enter your local MySQL username, password and port. Defaults are `root`, a blank password and port `3306`.
4. Visit [http://localhost/idfinder/](http://localhost/idfinder/). If Apache uses a different port, include it in the URL.
