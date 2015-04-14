CREATE TABLE "conversion_log" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "submit_time" text NOT NULL,
  "url" text NULL,
  "file_length" integer NULL,
  "addon_name" text NULL,
  "button" text NULL,
  "ip" text NOT NULL,
  "host" text NOT NULL,
  "user_agent" text NOT NULL
, "duration" text NULL);
