# Invoice Generator

Upload a Time Doctor "Projects & Tasks – Daily" CSV → tickets are combined → bill codes
(Jira **Application Code**) are fetched → download the PDF invoice + Excel sheet.

Each developer runs it locally in Docker. There is no login: the app has a single profile
(name, email, rate, Jira token…) edited on the **Settings** page.

## Run
```sh
cd tools/invoice
docker compose up -d
```
Open http://localhost:8080/tools/invoice/ and fill in **Settings** first.

Stop with `docker compose down`. Code edits show up on refresh; no rebuild needed.

## Layout
- Public entry: `webroot/tools/invoice/index.php`
- App code: `tools/invoice/`
- Data: `tools/invoice/storage/` (git-ignored): `invoice.sqlite` and `app_key`, the generated key
  that encrypts your rate and Jira token. Keep the two together; deleting `app_key` makes the
  saved token and rates unreadable.

## Update libraries
`composer install --no-dev` inside `tools/invoice/` (vendor/ is committed).
