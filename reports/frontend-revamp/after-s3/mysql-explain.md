# T3 review - EXPLAIN on MySQL 8.0.44

Fixture: 20.000 baris `submissions`, indeks `submissions_created_at_status_index`
dari migrasi T3.2 (`2026_11_13_000001_add_admin_dashboard_indexes`, tidak diubah).

```text
+---------------+
| variant       |
+---------------+
| OLD whereDate |
+---------------+
+----+-------------+-------------+------------+-------+---------------+-------------------------------------+---------+------+-------+----------+--------------------------+
| id | select_type | table       | partitions | type  | possible_keys | key                                 | key_len | ref  | rows  | filtered | Extra                    |
+----+-------------+-------------+------------+-------+---------------+-------------------------------------+---------+------+-------+----------+--------------------------+
|  1 | SIMPLE      | submissions | NULL       | index | NULL          | submissions_status_created_at_index | 6       | NULL | 20072 |   100.00 | Using where; Using index |
+----+-------------+-------------+------------+-------+---------------+-------------------------------------+---------+------+-------+----------+--------------------------+
+-------------+
| variant     |
+-------------+
| NEW between |
+-------------+
+----+-------------+-------------+------------+-------+--------------------------------------------------------------------------------------------------------------+--------------------------------------+---------+------+------+----------+----------------------------------------+
| id | select_type | table       | partitions | type  | possible_keys                                                                                                | key                                  | key_len | ref  | rows | filtered | Extra                                  |
+----+-------------+-------------+------------+-------+--------------------------------------------------------------------------------------------------------------+--------------------------------------+---------+------+------+----------+----------------------------------------+
|  1 | SIMPLE      | submissions | NULL       | range | submissions_user_id_created_at_index,submissions_status_created_at_index,submissions_created_at_status_index | submissions_user_id_created_at_index | 14      | NULL | 2229 |   100.00 | Using where; Using index for skip scan |
+----+-------------+-------------+------------+-------+--------------------------------------------------------------------------------------------------------------+--------------------------------------+---------+------+------+----------+----------------------------------------+
```

## Query nyata dari `DashboardMetrics` (30 hari)

Semua predikat jendela memakai `created_at between ? and ?`; `DATE()` hanya muncul di
SELECT/GROUP BY, sesuai instruksi review.

```sql
select DATE(created_at) as day, COUNT(*) as total from `submissions` where `created_at` between ? and ? group by DATE(created_at)
select count(*) as aggregate from `submissions` where `created_at` between ? and ? and `status` = ?
select input_type, COUNT(*) as total from `submissions` where `created_at` between ? and ? group by `input_type`
select stage, COUNT(*) as total from `submission_processing_events` where `outcome` = ? and `created_at` between ? and ? group by `stage`
```
