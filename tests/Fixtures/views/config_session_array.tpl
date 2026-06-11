inline_config=[{config key="app.array_val"}]
inline_session=[{session key="array_val"}]
assign_scalar=[{config key="app.scalar_val" assign="sc"}]
{config key="app.array_val" assign="cfg"}
assigned_config={$cfg.0}-{$cfg.1}
assigned_scalar={$sc}
