import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import Screen from '../components/Screen';
import { EstadoChip, PrioridadBadge } from '../components/Badges';
import EmptyState from '../components/EmptyState';
import { tickets as ticketsApi } from '../api/endpoints';
import type { Ticket } from '../api/types';
import {
  colors,
  ESTADOS_TICKET,
  estadoLabel,
  font,
  radius,
  spacing,
  type EstadoTicket,
} from '../theme/tokens';
import type { RootStackParamList } from '../navigation/RootNavigator';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, 'Tickets'>;
};

const PER_PAGE = 20;

export default function TicketsScreen({ navigation }: Props) {
  const [estado, setEstado] = useState<EstadoTicket | ''>('');
  const [items, setItems] = useState<Ticket[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [cargando, setCargando] = useState(true);
  const [cargandoMas, setCargandoMas] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const cargar = useCallback(async (estadoSel: EstadoTicket | '', pagina = 1) => {
    setError(null);
    if (pagina === 1) setCargando(true);
    try {
      const respuesta = await ticketsApi(estadoSel || undefined, pagina);
      setItems((prev) => (pagina === 1 ? respuesta.items : [...prev, ...respuesta.items]));
      setTotal(respuesta.total);
      setPage(pagina);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al cargar');
    } finally {
      setCargando(false);
      setCargandoMas(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      cargar(estado);
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [estado])
  );

  const hayMas = items.length < total;

  return (
    <Screen titulo="Tickets" subtitulo={`${total} tickets en total`}>
      {/* Filtros por estado */}
      <View style={styles.filtros}>
        <TouchableOpacity
          style={[styles.chipFiltro, estado === '' && styles.chipActivo]}
          onPress={() => setEstado('')}
        >
          <Text style={[styles.chipTexto, estado === '' && styles.chipTextoActivo]}>Todos</Text>
        </TouchableOpacity>
        {ESTADOS_TICKET.map((e) => (
          <TouchableOpacity
            key={e}
            style={[styles.chipFiltro, estado === e && styles.chipActivo]}
            onPress={() => setEstado(e)}
          >
            <Text style={[styles.chipTexto, estado === e && styles.chipTextoActivo]}>
              {estadoLabel[e]}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {cargando ? (
        <ActivityIndicator color={colors.blue} style={styles.cargando} />
      ) : error ? (
        <Text style={styles.error}>{error}</Text>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(t) => String(t.id)}
          refreshControl={
            <RefreshControl
              refreshing={cargando}
              onRefresh={() => cargar(estado)}
              tintColor={colors.blue}
            />
          }
          onEndReached={() => {
            if (hayMas && !cargandoMas) {
              setCargandoMas(true);
              cargar(estado, page + 1);
            }
          }}
          onEndReachedThreshold={0.3}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.fila}
              onPress={() => navigation.navigate('TicketDetalle', { id: item.id })}
            >
              <Text style={styles.folio}>{item.folio}</Text>
              <Text numberOfLines={2} style={styles.descripcion}>
                {item.descripcion}
              </Text>
              {item.activo_nombre ? (
                <Text style={styles.activo}>⚙ {item.activo_nombre}</Text>
              ) : null}
              <View style={styles.chips}>
                <PrioridadBadge prioridad={item.prioridad_ia} />
                <EstadoChip estado={item.estado} />
              </View>
            </TouchableOpacity>
          )}
          ListEmptyComponent={<EmptyState mensaje="No hay tickets con este filtro." />}
          ListFooterComponent={
            cargandoMas ? <ActivityIndicator color={colors.blue} style={styles.pie} /> : null
          }
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  filtros: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  chipFiltro: {
    borderRadius: radius.full,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    paddingVertical: 6,
  },
  chipActivo: {
    backgroundColor: colors.navy,
    borderColor: colors.navy,
  },
  chipTexto: { fontSize: 12, fontWeight: '600', color: colors.textMuted },
  chipTextoActivo: { color: colors.white },
  cargando: { marginTop: spacing.xxl },
  error: { color: colors.rose, textAlign: 'center', marginTop: spacing.xl },
  fila: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.lg,
    marginBottom: spacing.md,
    gap: 6,
    shadowColor: colors.text,
    shadowOpacity: 0.04,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
  },
  folio: {
    fontFamily: font.mono,
    fontSize: 12,
    fontWeight: '600',
    color: colors.blue,
  },
  descripcion: { fontSize: 14, color: colors.text, lineHeight: 20 },
  activo: { fontSize: 12, color: colors.textSoft },
  chips: { flexDirection: 'row', gap: spacing.sm, marginTop: 2 },
  pie: { paddingVertical: spacing.lg },
});
