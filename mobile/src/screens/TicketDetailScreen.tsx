import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import Screen from '../components/Screen';
import { EstadoChip, PrioridadBadge } from '../components/Badges';
import { cambiarEstado, ticket as ticketApi } from '../api/endpoints';
import type { Ticket } from '../api/types';
import {
  colors,
  ESTADOS_TICKET,
  estadoColor,
  estadoLabel,
  font,
  radius,
  spacing,
} from '../theme/tokens';

export default function TicketDetailScreen({ route }: { route: { params: { id: number } } }) {
  const { id } = route.params;
  const [ticket, setTicket] = useState<Ticket | null>(null);
  const [cargando, setCargando] = useState(true);
  const [actualizando, setActualizando] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const cargar = useCallback(async () => {
    try {
      setError(null);
      setTicket(await ticketApi(id));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al cargar');
    } finally {
      setCargando(false);
    }
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      cargar();
    }, [cargar])
  );

  const cambiar = async (estado: string) => {
    setActualizando(estado);
    try {
      setTicket(await cambiarEstado(id, estado));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo actualizar');
    } finally {
      setActualizando(null);
    }
  };

  if (cargando) {
    return (
      <Screen titulo="Ticket">
        <ActivityIndicator color={colors.blue} style={styles.cargando} />
      </Screen>
    );
  }

  if (error && !ticket) {
    return (
      <Screen titulo="Ticket">
        <Text style={styles.error}>{error}</Text>
      </Screen>
    );
  }

  return (
    <Screen titulo={ticket?.folio ?? 'Ticket'} subtitulo={ticket?.activo_nombre ?? undefined}>
      <ScrollView showsVerticalScrollIndicator={false}>
        {error ? <Text style={styles.error}>{error}</Text> : null}

        {ticket ? (
          <View style={styles.card}>
            <View style={styles.chips}>
              <PrioridadBadge prioridad={ticket.prioridad_ia} />
              <EstadoChip estado={ticket.estado} />
            </View>

            <Text style={styles.seccion}>Descripción</Text>
            <Text style={styles.texto}>{ticket.descripcion}</Text>

            <Text style={styles.seccion}>Clasificación IA</Text>
            <Text style={styles.texto}>
              {ticket.categoria_ia ? `${ticket.categoria_ia} · prioridad ${ticket.prioridad_ia}` : 'Sin clasificar'}
            </Text>

            <Text style={styles.seccion}>Fechas</Text>
            <Text style={styles.meta}>
              Apertura: {ticket.fecha_apertura}
              {ticket.fecha_cierre ? `\nCierre: ${ticket.fecha_cierre}` : ''}
            </Text>
          </View>
        ) : null}

        <Text style={styles.seccionCambio}>Cambiar estado</Text>
        <View style={styles.estados}>
          {ESTADOS_TICKET.map((e) => {
            const activo = ticket?.estado === e;
            return (
              <TouchableOpacity
                key={e}
                style={[
                  styles.botonEstado,
                  activo && { backgroundColor: estadoColor[e], borderColor: estadoColor[e] },
                ]}
                onPress={() => cambiar(e)}
                disabled={actualizando !== null || activo}
              >
                {actualizando === e ? (
                  <ActivityIndicator color={colors.white} size="small" />
                ) : (
                  <Text
                    style={[
                      styles.botonEstadoTexto,
                      activo && { color: colors.white },
                    ]}
                  >
                    {estadoLabel[e]}
                  </Text>
                )}
              </TouchableOpacity>
            );
          })}
        </View>
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  cargando: { marginTop: spacing.xxl },
  error: {
    color: colors.rose,
    backgroundColor: 'rgba(225,29,72,0.08)',
    borderColor: 'rgba(225,29,72,0.25)',
    borderWidth: 1,
    borderRadius: radius.sm,
    padding: spacing.md,
    marginBottom: spacing.md,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.lg,
    gap: spacing.sm,
    shadowColor: colors.text,
    shadowOpacity: 0.05,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 2 },
    elevation: 1,
  },
  chips: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.sm },
  seccion: {
    fontSize: 11,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    color: colors.textSoft,
    marginTop: spacing.sm,
  },
  texto: { fontSize: 15, color: colors.text, lineHeight: 22 },
  meta: { fontSize: 13, color: colors.textMuted, fontFamily: font.mono },
  seccionCambio: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.text,
    fontFamily: font.bold,
  },
  estados: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  botonEstado: {
    borderRadius: radius.full,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.lg,
    paddingVertical: 10,
  },
  botonEstadoTexto: { fontSize: 13, fontWeight: '600', color: colors.textMuted },
});
