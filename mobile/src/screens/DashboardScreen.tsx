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
import KpiCard from '../components/KpiCard';
import BarList from '../components/BarList';
import { EstadoChip, PrioridadBadge } from '../components/Badges';
import EmptyState from '../components/EmptyState';
import { dashboard, tickets as ticketsApi } from '../api/endpoints';
import type { DashboardResponse, Ticket } from '../api/types';
import { colors, font, radius, spacing } from '../theme/tokens';
import type { RootStackParamList } from '../navigation/RootNavigator';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, 'Dashboard'>;
};

export default function DashboardScreen({ navigation }: Props) {
  const [datos, setDatos] = useState<DashboardResponse | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const cargar = useCallback(async () => {
    try {
      setError(null);
      setDatos(await dashboard());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al cargar');
    } finally {
      setCargando(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      cargar();
    }, [cargar])
  );

  return (
    <Screen titulo="Dashboard" subtitulo="Resumen de tu organización">
      {cargando ? (
        <ActivityIndicator color={colors.blue} style={styles.cargando} />
      ) : error ? (
        <Text style={styles.error}>{error}</Text>
      ) : datos ? (
        <FlatList
          data={datos.tickets_recientes}
          keyExtractor={(t: Ticket) => String(t.id)}
          refreshControl={
            <RefreshControl refreshing={cargando} onRefresh={cargar} tintColor={colors.blue} />
          }
          ListHeaderComponent={
            <View style={styles.seccion}>
              <View style={styles.kpiFila}>
                <KpiCard etiqueta="Abiertos" valor={datos.kpis.tickets_abiertos} color={colors.blue} />
                <KpiCard etiqueta="Críticos" valor={datos.kpis.tickets_criticos} color={colors.rose} />
              </View>
              <View style={styles.kpiFila}>
                <KpiCard
                  etiqueta="Fuera servicio"
                  valor={datos.kpis.activos_fuera_servicio}
                  color={colors.amber}
                />
                <KpiCard
                  etiqueta="Sin stock"
                  valor={datos.kpis.productos_sin_stock}
                  color={colors.textSoft}
                />
              </View>

              <View style={styles.card}>
                <Text style={styles.cardTitulo}>Tickets por estado</Text>
                <BarList filas={datos.tickets_por_estado} />
              </View>

              <View style={styles.seccionCabecera}>
                <Text style={styles.cardTitulo}>Tickets recientes</Text>
                <TouchableOpacity onPress={() => navigation.navigate('Tickets')}>
                  <Text style={styles.verTodos}>Ver todos</Text>
                </TouchableOpacity>
              </View>
            </View>
          }
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.ticketFila}
              onPress={() => navigation.navigate('TicketDetalle', { id: item.id })}
            >
              <View style={styles.ticketInfo}>
                <Text style={styles.folio}>{item.folio}</Text>
                <Text numberOfLines={2} style={styles.descripcion}>
                  {item.descripcion}
                </Text>
                <View style={styles.chipsFila}>
                  <PrioridadBadge prioridad={item.prioridad_ia} />
                  <EstadoChip estado={item.estado} />
                </View>
              </View>
            </TouchableOpacity>
          )}
          ListEmptyComponent={<EmptyState mensaje="Sin tickets registrados aún." />}
          ListFooterComponent={
            <TouchableOpacity
              style={styles.botonSecundario}
              onPress={() => navigation.navigate('Chat')}
            >
              <Text style={styles.botonSecundarioTexto}>💬 Preguntar a ORION</Text>
            </TouchableOpacity>
          }
        />
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  cargando: { marginTop: spacing.xxl },
  error: { color: colors.rose, textAlign: 'center', marginTop: spacing.xl },
  seccion: { gap: spacing.lg },
  kpiFila: { flexDirection: 'row', gap: spacing.md },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.lg,
    gap: spacing.md,
    shadowColor: colors.text,
    shadowOpacity: 0.05,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 2 },
    elevation: 1,
  },
  cardTitulo: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.text,
    fontFamily: font.bold,
  },
  seccionCabecera: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  verTodos: { color: colors.blue, fontWeight: '700', fontSize: 13 },
  ticketFila: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.lg,
    marginBottom: spacing.md,
    shadowColor: colors.text,
    shadowOpacity: 0.04,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
  },
  ticketInfo: { gap: 6 },
  folio: {
    fontFamily: font.mono,
    fontSize: 12,
    fontWeight: '600',
    color: colors.blue,
  },
  descripcion: { fontSize: 14, color: colors.text, lineHeight: 20 },
  chipsFila: { flexDirection: 'row', gap: spacing.sm, marginTop: 2 },
  botonSecundario: {
    backgroundColor: colors.navy,
    borderRadius: radius.md,
    padding: spacing.lg,
    alignItems: 'center',
    marginTop: spacing.sm,
  },
  botonSecundarioTexto: { color: colors.white, fontWeight: '700', fontSize: 14 },
});
