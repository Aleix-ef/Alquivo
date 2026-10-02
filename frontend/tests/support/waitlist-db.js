import { DatabaseSync } from "node:sqlite";
import { readFile } from "node:fs/promises";

// Execute real SQLite SQL using the D1 prepare/bind/batch API shape. No network.
export async function testWaitlistDb() {
  const sqlite = new DatabaseSync(":memory:");
  sqlite.exec(
    await readFile(
      new URL("../../migrations/waitlist/0001_waitlist.sql", import.meta.url),
      "utf8",
    ),
  );
  return {
    sqlite,
    prepare(sql) {
      const statement = sqlite.prepare(sql);
      return {
        bind(...args) {
          return {
            async all() {
              return { success: true, results: statement.all(...args) };
            },
          };
        },
      };
    },
    async batch(statements) {
      sqlite.exec("BEGIN");
      try {
        const results = [];
        for (const statement of statements) results.push(await statement.all());
        sqlite.exec("COMMIT");
        return results;
      } catch (error) {
        sqlite.exec("ROLLBACK");
        throw error;
      }
    },
    signups() {
      return sqlite
        .prepare("SELECT * FROM waitlist_signups ORDER BY created_at")
        .all();
    },
    rates() {
      return sqlite.prepare("SELECT * FROM waitlist_rate_limits").all();
    },
    close() {
      sqlite.close();
    },
  };
}
