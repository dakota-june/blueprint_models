# Changelog

## 1.0.0

- Modern attributes (`Fillable`, `Hidden`, `Table`, `Connection`, `WithoutTimestamps`, `WithoutIncrementing`, `Unguarded`), chosen automatically by Laravel version unless set.
- `default:` modifiers become `protected $attributes`.
- Docblocks: class `@property` / `@property-read`, relationship summaries and `@return` generics, `$attributes` and `casts()`, the `@use HasFactory<>` line.
- `_schema()`: the table definition, commented out, built by Blueprint's own migration code.
- `extends:` and `use:` in a model; `meta: description:`.
- Runs on Blueprint 2.10 through master.
