import AppLayout from '@/layouts/app-layout';

type SeriesPoint = { hour: string; total: number };
type CountRow = { rule_id?: string; ip?: string; total: number };
type SiteRow = {
    id: number;
    name: string;
    hostname: string;
    events_count: number;
};
type NodeRow = {
    id: number;
    uuid: string;
    name: string;
    health: string;
    agent_version: string | null;
    config_version: number | null;
    last_seen_at: string | null;
};

export default function Dashboard({
    stats,
    series,
    attackTypes,
    topIps,
    sites,
    nodes,
}: {
    stats: {
        detected: number;
        blocked: number;
        nodes: number;
        online: number;
    };
    series: SeriesPoint[];
    attackTypes: CountRow[];
    topIps: CountRow[];
    sites: SiteRow[];
    nodes: NodeRow[];
}) {
    const max = Math.max(1, ...series.map((point) => point.total));

    return (
        <AppLayout title="ダッシュボード">
            <h1 className="mb-6 text-2xl font-semibold">ダッシュボード</h1>
            <div className="mb-8 grid gap-4 sm:grid-cols-4">
                <Stat label="検知数（24h）" value={stats.detected} />
                <Stat label="遮断数（24h）" value={stats.blocked} />
                <Stat label="ノード数" value={stats.nodes} />
                <Stat label="オンライン" value={stats.online} />
            </div>

            <section className="mb-8 rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <h2 className="mb-4 text-sm font-medium text-zinc-300">
                    時系列（1時間ごと）
                </h2>
                {series.length === 0 ? (
                    <p className="text-sm text-zinc-500">まだイベントはありません。</p>
                ) : (
                    <div className="flex h-40 items-end gap-1">
                        {series.map((point) => (
                            <div
                                key={point.hour}
                                className="flex-1 bg-emerald-600"
                                style={{
                                    height: `${Math.max(8, (point.total / max) * 100)}%`,
                                }}
                                title={`${point.hour}: ${point.total}`}
                            />
                        ))}
                    </div>
                )}
            </section>

            <div className="grid gap-6 lg:grid-cols-2">
                <ListCard
                    title="攻撃種別"
                    rows={attackTypes.map((row) => ({
                        label: row.rule_id ?? '-',
                        total: row.total,
                    }))}
                />
                <ListCard
                    title="攻撃元IP"
                    rows={topIps.map((row) => ({
                        label: row.ip ?? '-',
                        total: row.total,
                    }))}
                />
                <ListCard
                    title="サイト別イベント"
                    rows={sites.map((site) => ({
                        label: `${site.name} (${site.hostname})`,
                        total: site.events_count,
                    }))}
                />
                <section className="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <h2 className="mb-4 text-sm font-medium text-zinc-300">
                        ノード
                    </h2>
                    <ul className="space-y-2 text-sm">
                        {nodes.map((node) => (
                            <li
                                key={node.id}
                                className="flex items-center justify-between"
                            >
                                <span>
                                    {node.name}
                                    <span className="ml-2 text-zinc-500">
                                        {node.agent_version ?? '未接続'} / v
                                        {node.config_version ?? '-'}
                                    </span>
                                </span>
                                <span
                                    className={
                                        node.health === 'online'
                                            ? 'text-emerald-400'
                                            : 'text-zinc-500'
                                    }
                                >
                                    {node.health}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </AppLayout>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
            <p className="text-sm text-zinc-400">{label}</p>
            <p className="mt-2 text-3xl font-semibold">{value}</p>
        </div>
    );
}

function ListCard({
    title,
    rows,
}: {
    title: string;
    rows: { label: string; total: number }[];
}) {
    return (
        <section className="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <h2 className="mb-4 text-sm font-medium text-zinc-300">{title}</h2>
            {rows.length === 0 ? (
                <p className="text-sm text-zinc-500">データなし</p>
            ) : (
                <ul className="space-y-2 text-sm">
                    {rows.map((row) => (
                        <li
                            key={row.label}
                            className="flex items-center justify-between"
                        >
                            <span className="truncate pr-4">{row.label}</span>
                            <span className="text-zinc-400">{row.total}</span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
