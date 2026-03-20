<script setup>
import { computed } from 'vue';
import FlowNodeCard from './FlowNodeCard.vue';

const props = defineProps({
    nodes: {
        type: Array,
        default: () => [],
    },
    chain: {
        type: Array,
        default: () => [],
    },
});

const nodesByDepth = computed(() => {
    const grouped = new Map();
    props.nodes.forEach((node) => {
        const depth = Number.isFinite(node.depth) ? node.depth : 0;
        if (!grouped.has(depth)) {
            grouped.set(depth, []);
        }
        grouped.get(depth).push(node);
    });

    return Array.from(grouped.entries())
        .sort((a, b) => a[0] - b[0])
        .map(([depth, list]) => ({ depth, nodes: list }));
});

const maxColumns = computed(() => Math.max(nodesByDepth.value.length, 1));

const chainNodes = computed(() => {
    const nodeMap = new Map(props.nodes.map((node) => [node.id, node]));
    return props.chain.map((id) => nodeMap.get(id)).filter(Boolean);
});

const chainIds = computed(() => new Set(props.chain));
</script>

<template>
    <div class="space-y-6">
        <div>
            <div class="text-sm font-semibold text-slate-700">Flow Path</div>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                <template v-for="(node, index) in chainNodes" :key="node.id">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        {{ node.name }}
                    </span>
                    <span v-if="index < chainNodes.length - 1" class="text-slate-400">→</span>
                </template>
                <span v-if="chainNodes.length === 0" class="text-xs text-slate-400">
                    No chain data available.
                </span>
            </div>
        </div>

        <div>
            <div class="text-sm font-semibold text-slate-700">Flow Tree</div>
            <div
                class="mt-4 grid gap-6"
                :style="{ gridTemplateColumns: `repeat(${maxColumns}, minmax(0, 1fr))` }"
            >
                <div v-for="group in nodesByDepth" :key="group.depth" class="space-y-3">
                    <div class="text-xs uppercase tracking-wide text-slate-400">
                        Depth {{ group.depth }}
                    </div>
                    <FlowNodeCard
                        v-for="node in group.nodes"
                        :key="node.id"
                        :node="node"
                        :highlight="chainIds.has(node.id)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
