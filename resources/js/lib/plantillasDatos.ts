import type { CompanySettingsForm } from '@/types/settings';

/** Mismas plantillas por defecto que el backend (PlantillasDatosEmpresa). */
export const PLANTILLAS_DATOS_DEFECTO: NonNullable<CompanySettingsForm['plantillas_datos']> = {
    motorizado: {
        campo_0: '✅ NOMBRE COMPLETO',
        campo_1: '✅ CELULAR',
        campo_2: '✅ DIRECCIÓN',
        campo_3: '✅ UBICACIÓN ACTUAL',
    },
    shalom: {
        campo_0: '✅ Nombre completo',
        campo_1: '✅ Número de DNI',
        campo_2: '✅ Número de celular',
        campo_3: '✅ Sede exacta de shalom',
    },
};

export function normalizarPlantillasDatos(
    plantillas: unknown,
): NonNullable<CompanySettingsForm['plantillas_datos']> {
    if (!plantillas || Array.isArray(plantillas)) {
        return structuredClone(PLANTILLAS_DATOS_DEFECTO);
    }

    const motorizado =
        plantillas.motorizado && typeof plantillas.motorizado === 'object'
            ? (plantillas.motorizado as Record<string, string>)
            : {};
    const shalom =
        plantillas.shalom && typeof plantillas.shalom === 'object'
            ? (plantillas.shalom as Record<string, string>)
            : {};

    if (Object.keys(motorizado).length === 0 && Object.keys(shalom).length === 0) {
        return structuredClone(PLANTILLAS_DATOS_DEFECTO);
    }

    return {
        motorizado:
            Object.keys(motorizado).length > 0 ? motorizado : structuredClone(PLANTILLAS_DATOS_DEFECTO.motorizado),
        shalom: Object.keys(shalom).length > 0 ? shalom : structuredClone(PLANTILLAS_DATOS_DEFECTO.shalom),
    };
}
