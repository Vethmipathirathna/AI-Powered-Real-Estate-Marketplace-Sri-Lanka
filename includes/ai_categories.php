<?php
/**
 * Model category lists exported from the fitted sklearn pipeline.
 *
 * District-to-area mapping is not available in the saved model, so areas are
 * offered independently. Users may optionally enter a custom area name when the
 * trained list does not contain an exact match.
 */
declare(strict_types=1);

if (!function_exists('ai_model_districts')) {
    /** @return list<string> */
    function ai_model_districts(): array
    {
        return [
            'Ampara', 'Anuradhapura', 'Badulla', 'Batticaloa', 'Colombo', 'Galle', 'Gampaha',
            'Hambantota', 'Jaffna', 'Kalutara', 'Kandy', 'Kegalle', 'Kilinochchi', 'Kurunegala',
            'Mannar', 'Matale', 'Matara', 'Monaragala', 'Mullaitivu', 'Nuwara Eliya',
            'Polonnaruwa', 'Puttalam', 'Ratnapura', 'Trincomalee', 'Vavuniya',
        ];
    }
}

if (!function_exists('ai_model_areas')) {
    /** @return list<string> */
    function ai_model_areas(): array
    {
        return [
            'Akurugoda', 'Ambalantota', 'Ampara Central', 'Badulla Town', 'Bambalapitiya',
            'Bandarawela', 'Batticaloa Town', 'Beruwala', 'Borella', 'China Bay', 'Chunnakam',
            'Dehiwala', 'Eravur', 'Galle Fort', 'Gampaha Town', 'Gatambe', 'Hali Ela',
            'Hambantota Town', 'Hikkaduwa', 'Ja-Ela', 'Jaffna Town', 'Kadawatha', 'Kallady',
            'Kalutara North', 'Kandy City', 'Karapitiya', 'Katugastota', 'Kegalle Central',
            'Kilinochchi Central', 'Kokuvil', 'Kollupitiya', 'Kurunegala Town', 'Kuruwita',
            'Madawachchiya', 'Mannar Central', 'Matale Central', 'Matara Town', 'Melsiripura',
            'Monaragala Central', 'Mount Lavinia', 'Mullaitivu Central', 'Nallur', 'Narahenpita',
            'Negombo', 'New Town', 'Nilaveli', 'Nugegoda', 'Nupe', 'Nuwara Eliya Central',
            'Nuwaragam Palatha', 'Panadura', 'Pannala', 'Pelmadulla', 'Peradeniya',
            'Polgahawela', 'Polonnaruwa Central', 'Puttalam Central', 'Ragama', 'Rajagiriya',
            'Ratnapura Town', 'Tangalle', 'Tennekumbura', 'Unawatuna', 'Uppuveli',
            'Vavuniya Central', 'Wadduwa', 'Wattala', 'Weligama', 'Wellawatte',
        ];
    }
}

if (!function_exists('ai_water_supply_options')) {
    /** @return list<string> */
    function ai_water_supply_options(): array
    {
        return ['Pipe-borne', 'Well', 'Both'];
    }
}

if (!function_exists('ai_electricity_options')) {
    /** @return list<string> */
    function ai_electricity_options(): array
    {
        return ['Single phase', 'Three phase'];
    }
}
